import 'package:flutter/material.dart';
import '../../../app/routes.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../core/widgets/primary_button.dart';
import '../../../core/widgets/secondary_button.dart';
import '../../../data/models/delivery.dart';
import '../../../data/repositories/delivery_repository.dart';

/// Full detail for a single delivery dispatch — status, rider/vehicle,
/// every stop with its own status, and real status-update actions
/// (persisted through [DeliveryRepository]).
///
/// Shared by both the Owner Delivery Management module and the rider-
/// facing flow (Assigned Deliveries → **Delivery Details** → Optimized
/// Route → Start Delivery → ...). When no stop has started yet, a
/// prominent "Start Delivery" button appears to kick off that rider flow.
class DeliveryDetailsScreen extends StatefulWidget {
  final Delivery delivery;

  const DeliveryDetailsScreen({super.key, required this.delivery});

  @override
  State<DeliveryDetailsScreen> createState() => _DeliveryDetailsScreenState();
}

class _DeliveryDetailsScreenState extends State<DeliveryDetailsScreen> {
  final Set<String> _updatingStops = {};

  Future<void> _markDelivered(DeliveryStop stop) async {
    final previousStatus = stop.status;
    setState(() {
      _updatingStops.add(stop.id);
      stop.status = StopStatus.delivered;
    });
    try {
      final updated = await DeliveryRepository.instance.updateDeliveryStop(
        stopId: stop.id,
        status: 'delivered',
      );
      StaffStore.instance.applyDelivery(updated);
      if (!mounted) return;
      setState(() {
        widget.delivery.status = updated.status;
        _updatingStops.remove(stop.id);
      });
      if (widget.delivery.isComplete) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${widget.delivery.id} marked completed — all stops delivered.')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        stop.status = previousStatus;
        _updatingStops.remove(stop.id);
      });
      AppErrors.showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final delivery = widget.delivery;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: MelaiAppBar(
        title: delivery.id,
        showBack: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.map_outlined),
            onPressed: () => Navigator.of(context).pushNamed(AppRoutes.routeMap, arguments: delivery),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(delivery.branch, style: AppTextStyles.titleMd)),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: delivery.status.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                        child: Text(delivery.status.label, style: AppTextStyles.labelSm.copyWith(color: delivery.status.color)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(Icons.local_shipping_outlined, size: 16, color: AppColors.textSecondary),
                      const SizedBox(width: 6),
                      Text('${delivery.vehicle} • ${delivery.riderName}', style: AppTextStyles.bodyMd),
                    ],
                  ),
                  const Divider(height: 20),
                  Row(
                    children: [
                      Expanded(child: _Stat(label: 'Distance', value: '${delivery.totalDistanceKm.toStringAsFixed(1)} km')),
                      Expanded(child: _Stat(label: 'Time', value: '${delivery.totalTimeMinutes} min')),
                      Expanded(child: _Stat(label: 'Progress', value: '${delivery.deliveredCount}/${delivery.stops.length}')),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            if (delivery.stops.every((s) => s.status == StopStatus.pending))
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                child: PrimaryButton(
                  label: 'Start Delivery',
                  icon: Icons.play_circle_outline_rounded,
                  onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryRoute, arguments: delivery),
                ),
              ),
            Text('Delivery Stops', style: AppTextStyles.headlineSm),
            const SizedBox(height: AppSpacing.sm),
            for (int i = 0; i < delivery.stops.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 26,
                            height: 26,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(color: delivery.stops[i].status.color.withValues(alpha: 0.15), shape: BoxShape.circle),
                            child: Text('${i + 1}', style: AppTextStyles.labelSm.copyWith(color: delivery.stops[i].status.color)),
                          ),
                          const SizedBox(width: 8),
                          Expanded(child: Text(delivery.stops[i].customerName, style: AppTextStyles.labelLg)),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(color: delivery.stops[i].status.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                            child: Text(delivery.stops[i].status.label, style: AppTextStyles.labelSm.copyWith(color: delivery.stops[i].status.color)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(delivery.stops[i].address, style: AppTextStyles.bodySm),
                      Text('${delivery.stops[i].orderId} • ETA ${delivery.stops[i].eta}', style: AppTextStyles.bodySm),
                      if (delivery.stops[i].status != StopStatus.delivered) ...[
                        const SizedBox(height: 10),
                        SecondaryButton(
                          label: 'Mark as Delivered',
                          icon: Icons.check_circle_outline_rounded,
                          onPressed: _updatingStops.contains(delivery.stops[i].id)
                              ? null
                              : () => _markDelivered(delivery.stops[i]),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryManifest, arguments: delivery),
                    icon: const Icon(Icons.checklist_rounded, size: 16),
                    label: const Text('View Manifest'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pushNamed(AppRoutes.routeOptimization, arguments: delivery),
                    icon: const Icon(Icons.route_outlined, size: 16),
                    label: const Text('View Route'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label;
  final String value;

  const _Stat({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: AppTextStyles.bodySm),
        Text(value, style: AppTextStyles.labelLg),
      ],
    );
  }
}
