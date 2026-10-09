import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../app/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../data/models/owner_sales.dart';
import '../widgets/owner_sales_scope.dart';
import '../widgets/stat_card.dart';

/// Sales Analytics — last-30-day totals and the top products by item sales,
/// both computed by the database (`owner_sales_summary`).
///
/// Revenue by category and the payment-method mix are not shown: the backend
/// does not report either yet, and the app does not estimate them.
class SalesAnalyticsScreen extends StatelessWidget {
  const SalesAnalyticsScreen({super.key});

  static final _money = NumberFormat('#,##0.00');

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: const MelaiAppBar(title: 'Sales Analytics', showBack: true),
      body: SafeArea(
        child: OwnerSalesScope(days: 30, builder: _body),
      ),
    );
  }

  Widget _body(BuildContext context, OwnerSalesSummary summary) {
    final revenue = summary.branches.fold<double>(0, (s, b) => s + b.windowRevenue);
    final orders = summary.branches.fold<int>(0, (s, b) => s + b.ordersWindow);
    final avgOrder = orders == 0 ? 0.0 : revenue / orders;
    final top = summary.products.take(5).toList();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Text('Last 30 days', style: AppTextStyles.headlineSm),
        const SizedBox(height: AppSpacing.sm),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.6,
          children: [
            OwnerStatCard(label: 'Gross Sales', value: '₱${_money.format(revenue)}', icon: Icons.payments_outlined),
            OwnerStatCard(label: 'Orders', value: '$orders', sub: 'Completed', icon: Icons.receipt_long_outlined),
            OwnerStatCard(label: 'Avg Order Value', value: '₱${_money.format(avgOrder)}', icon: Icons.shopping_bag_outlined),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Top Products by Item Sales', style: AppTextStyles.headlineSm),
            TextButton(
              onPressed: () => Navigator.of(context).pushNamed(AppRoutes.ownerProductPerformance),
              child: const Text('View All'),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        if (top.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(child: Text('No sales in the last 30 days.')),
          )
        else
          for (final p in top)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border)),
                child: Row(
                  children: [
                    Expanded(child: Text(p.productName, style: AppTextStyles.bodyMd, maxLines: 1, overflow: TextOverflow.ellipsis)),
                    Text('${p.units} sold', style: AppTextStyles.bodySm),
                    const SizedBox(width: 10),
                    Text('₱${_money.format(p.revenue)}', style: AppTextStyles.labelLg.copyWith(color: AppColors.primary)),
                  ],
                ),
              ),
            ),
        const SizedBox(height: AppSpacing.lg),
        const _UnavailableCard(
          title: 'Revenue by Category',
          message: 'The backend does not report sales by category yet.',
        ),
        const SizedBox(height: AppSpacing.sm),
        const _UnavailableCard(
          title: 'Payment Method Mix',
          message: 'The backend does not report sales by payment method yet.',
        ),
      ],
    );
  }
}

class _UnavailableCard extends StatelessWidget {
  final String title;
  final String message;

  const _UnavailableCard({required this.title, required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainerLow,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: AppTextStyles.labelLg),
          const SizedBox(height: 4),
          Text('Not available yet. $message', style: AppTextStyles.bodySm),
        ],
      ),
    );
  }
}
