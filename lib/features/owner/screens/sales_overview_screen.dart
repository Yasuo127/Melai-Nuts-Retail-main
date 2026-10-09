import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../app/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../data/models/owner_sales.dart';
import '../widgets/owner_sales_scope.dart';

/// Sales Overview — Today / Last 7 Days / Last 30 Days totals with a
/// per-branch breakdown. Every figure is computed by the database
/// (`owner_sales_summary`); "30 days" is a rolling window, not a calendar month.
class SalesOverviewScreen extends StatefulWidget {
  const SalesOverviewScreen({super.key});

  @override
  State<SalesOverviewScreen> createState() => _SalesOverviewScreenState();
}

enum _Period { today, week, month }

class _SalesOverviewScreenState extends State<SalesOverviewScreen> {
  static final _money = NumberFormat('#,##0.00');

  _Period _period = _Period.today;

  double _revenue(OwnerBranchSales b) => switch (_period) {
        _Period.today => b.todayRevenue,
        _Period.week => b.weekRevenue,
        _Period.month => b.windowRevenue,
      };

  int _orders(OwnerBranchSales b) => switch (_period) {
        _Period.today => b.ordersToday,
        _Period.week => b.ordersWeek,
        _Period.month => b.ordersWindow,
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: MelaiAppBar(
        title: 'Sales Overview',
        showBack: true,
        actions: [
          IconButton(icon: const Icon(Icons.query_stats_rounded), onPressed: () => Navigator.of(context).pushNamed(AppRoutes.ownerSalesAnalytics)),
        ],
      ),
      body: SafeArea(
        child: OwnerSalesScope(days: 30, builder: _body),
      ),
    );
  }

  Widget _body(BuildContext context, OwnerSalesSummary summary) {
    final branches = summary.branches;
    final revenue = branches.fold<double>(0, (s, b) => s + _revenue(b));
    final orders = branches.fold<int>(0, (s, b) => s + _orders(b));
    final avgOrder = orders == 0 ? 0.0 : revenue / orders;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        SegmentedButton<_Period>(
          segments: const [
            ButtonSegment(value: _Period.today, label: Text('Today')),
            ButtonSegment(value: _Period.week, label: Text('7 Days')),
            ButtonSegment(value: _Period.month, label: Text('30 Days')),
          ],
          selected: {_period},
          onSelectionChanged: (s) => setState(() => _period = s.first),
        ),
        const SizedBox(height: AppSpacing.lg),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('GROSS SALES', style: AppTextStyles.labelSm),
              Text('₱${_money.format(revenue)}', style: AppTextStyles.headlineLg.copyWith(color: AppColors.roleOwner)),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Orders', style: AppTextStyles.bodySm),
                        Text('$orders', style: AppTextStyles.titleMd),
                      ],
                    ),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Avg Order Value', style: AppTextStyles.bodySm),
                        Text('₱${_money.format(avgOrder)}', style: AppTextStyles.titleMd),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        Text('Per-Branch Breakdown', style: AppTextStyles.headlineSm),
        const SizedBox(height: AppSpacing.sm),
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border)),
          child: branches.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(14),
                  child: Text('No branches found.', style: AppTextStyles.bodyMd),
                )
              : Column(
                  children: [
                    for (var i = 0; i < branches.length; i++) ...[
                      Padding(
                        padding: const EdgeInsets.all(14),
                        child: Row(
                          children: [
                            Expanded(child: Text(branches[i].name, style: AppTextStyles.bodyMd)),
                            Text('₱${_money.format(_revenue(branches[i]))}', style: AppTextStyles.labelLg),
                          ],
                        ),
                      ),
                      if (i != branches.length - 1) const Divider(height: 1),
                    ],
                  ],
                ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          'Completed orders only, including delivery fees and after discounts. Days are Asia/Manila days.',
          style: AppTextStyles.bodySm,
        ),
        const SizedBox(height: AppSpacing.lg),
        OutlinedButton.icon(
          onPressed: () => Navigator.of(context).pushNamed(AppRoutes.ownerSalesTrends),
          icon: const Icon(Icons.show_chart_rounded, size: 16),
          label: const Text('Trends'),
        ),
      ],
    );
  }
}
