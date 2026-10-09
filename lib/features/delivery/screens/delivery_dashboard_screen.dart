import 'package:flutter/material.dart';
import '../../../app/routes.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../core/widgets/primary_button.dart';
import '../../../core/widgets/staff_data_scope.dart';
import '../../../core/widgets/state_views.dart';
import '../../../data/dummy_data/dummy_deliveries.dart';
import '../../../data/models/delivery.dart';

/// Owner-facing Delivery Management dashboard — dispatch oversight across
/// all branches: active deliveries, quick stats, and entry points into
/// creating a new delivery, route optimization, manifests, and history.
///
/// Real deliveries loaded from the database. There is still no Google Maps
/// API or courier API — per-stop distance/time/ETA are a simple, honestly
/// labeled estimate, not real routing (see `staff_create_delivery`).
class DeliveryDashboardScreen extends StatelessWidget {
  const DeliveryDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StaffDataScope(builder: (context, store) => _content(context, store));
  }

  Widget _content(BuildContext context, StaffStore store) {
    if (!store.deliveriesState.loaded && !store.deliveriesState.loading && store.deliveriesState.error == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => store.refreshDeliveries());
    }

    final active = activeDeliveries;
    final completedToday = kDeliveries.where((d) => d.status == DeliveryStatus.completed).length;
    final totalDistanceToday = kDeliveries.fold<double>(0, (sum, d) => sum + d.totalDistanceKm);
    final totalStopsToday = kDeliveries.fold<int>(0, (sum, d) => sum + d.stops.length);
    final deliveredStops = kDeliveries.fold<int>(0, (sum, d) => sum + d.deliveredCount);
    final onTimeRate = totalStopsToday == 0 ? 0.0 : deliveredStops / totalStopsToday * 100;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: MelaiAppBar(
        title: 'Delivery Management',
        showBack: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.history_rounded),
            tooltip: 'Delivery History',
            onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryHistory),
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: store.refreshDeliveries,
          child: DataStateView(
            isLoading: store.deliveriesState.busy,
            error: store.deliveriesState.error,
            isEmpty: kDeliveries.isEmpty,
            emptyTitle: 'No deliveries yet',
            emptyMessage: 'Create a delivery to get started.',
            builder: (context) => ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.6,
              children: [
                _StatTile(icon: Icons.local_shipping_outlined, label: 'Active Deliveries', value: '${active.length}', color: AppColors.roleDelivery),
                _StatTile(icon: Icons.check_circle_outline_rounded, label: 'Completed', value: '$completedToday', color: AppColors.success),
                _StatTile(icon: Icons.route_outlined, label: 'Total Distance', value: '${totalDistanceToday.toStringAsFixed(1)} km', color: AppColors.primary),
                _StatTile(icon: Icons.timer_outlined, label: 'On-Time Rate', value: '${onTimeRate.toStringAsFixed(0)}%', color: onTimeRate >= 80 ? AppColors.success : AppColors.warning),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            PrimaryButton(
              label: '+ Create Delivery',
              icon: Icons.add_road_rounded,
              onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryCreate),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text('Active Dispatches', style: AppTextStyles.headlineSm),
            const SizedBox(height: AppSpacing.sm),
            if (active.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text('No active deliveries right now.', style: AppTextStyles.bodyMd),
              )
            else
              for (final delivery in active)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _DeliveryCard(
                    delivery: delivery,
                    onTap: () => Navigator.of(context).pushNamed(AppRoutes.deliveryDetails, arguments: delivery),
                  ),
                ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryHistory),
                    icon: const Icon(Icons.history_rounded, size: 16),
                    label: const Text('View History'),
                  ),
                ),
              ],
            ),
          ],
        ),
          ),
        ),
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _StatTile({required this.icon, required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.sm,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: color),
          const SizedBox(height: 6),
          Text(value, style: AppTextStyles.headlineSm.copyWith(color: color)),
          Text(label, style: AppTextStyles.bodySm),
        ],
      ),
    );
  }
}

class _DeliveryCard extends StatelessWidget {
  final Delivery delivery;
  final VoidCallback onTap;

  const _DeliveryCard({required this.delivery, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(delivery.id, style: AppTextStyles.labelLg)),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: delivery.status.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                  child: Text(delivery.status.label, style: AppTextStyles.labelSm.copyWith(color: delivery.status.color)),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text('${delivery.branch} • ${delivery.vehicle} • ${delivery.riderName}', style: AppTextStyles.bodySm),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: _MiniStat(icon: Icons.pin_drop_outlined, label: '${delivery.stops.length} stops')),
                Expanded(child: _MiniStat(icon: Icons.route_outlined, label: '${delivery.totalDistanceKm.toStringAsFixed(1)} km')),
                Expanded(child: _MiniStat(icon: Icons.schedule_outlined, label: '${delivery.totalTimeMinutes} min')),
              ],
            ),
            const SizedBox(height: 6),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(
                value: delivery.stops.isEmpty ? 0 : delivery.deliveredCount / delivery.stops.length,
                minHeight: 6,
                backgroundColor: AppColors.border,
                color: AppColors.success,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final IconData icon;
  final String label;

  const _MiniStat({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 14, color: AppColors.textSecondary),
        const SizedBox(width: 4),
        Expanded(child: Text(label, style: AppTextStyles.bodySm, overflow: TextOverflow.ellipsis)),
      ],
    );
  }
}
