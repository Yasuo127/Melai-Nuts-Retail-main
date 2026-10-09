import 'package:flutter/material.dart';
import '../../../app/routes.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/staff_data_scope.dart';
import '../../../data/dummy_data/dummy_deliveries.dart';
import '../../../data/models/delivery.dart';
import 'delivery_profile_screen.dart';

/// Delivery Personnel Portal — the rider's own assigned routes, delivery
/// history, and profile (incl. Log Out). Reuses the shared [Delivery] model and links into
/// the same Route Optimization / Route Map / Manifest screens used by the
/// Owner's Delivery Management module, so both roles see one consistent
/// picture of each real dispatch.
class DeliveryPortalScreen extends StatefulWidget {
  const DeliveryPortalScreen({super.key});

  @override
  State<DeliveryPortalScreen> createState() => _DeliveryPortalScreenState();
}

class _DeliveryPortalScreenState extends State<DeliveryPortalScreen> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    return StaffDataScope(builder: (context, store) => _content(context, store));
  }

  Widget _content(BuildContext context, StaffStore store) {
    if (!store.deliveriesState.loaded && !store.deliveriesState.loading && store.deliveriesState.error == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => store.refreshDeliveries());
    }
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Row(
          children: [
            const CircleAvatar(
              radius: 16,
              backgroundColor: AppColors.roleDelivery,
              child: Icon(Icons.local_shipping_rounded, size: 16, color: Colors.white),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Rider Portal', style: AppTextStyles.labelLg),
                Text('Delivery Rider', style: AppTextStyles.bodySm),
              ],
            ),
          ],
        ),
      ),
      body: IndexedStack(
        index: _currentIndex,
        children: const [
          _MyRouteTab(),
          _RiderHistoryTab(),
          DeliveryProfileBody(),
        ],
      ),
      bottomNavigationBar: NavigationBarTheme(
        data: NavigationBarThemeData(
          backgroundColor: Colors.white,
          indicatorColor: AppColors.roleDelivery.withValues(alpha: 0.15),
          surfaceTintColor: Colors.transparent,
          labelTextStyle: WidgetStateProperty.resolveWith((states) {
            if (states.contains(WidgetState.selected)) {
              return AppTextStyles.labelMd.copyWith(color: AppColors.roleDelivery);
            }
            return AppTextStyles.labelMd;
          }),
        ),
        child: NavigationBar(
          selectedIndex: _currentIndex,
          onDestinationSelected: (i) => setState(() => _currentIndex = i),
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.route_outlined),
              selectedIcon: Icon(Icons.route_rounded, color: AppColors.roleDelivery),
              label: 'My Route',
            ),
            NavigationDestination(
              icon: Icon(Icons.history_rounded),
              selectedIcon: Icon(Icons.history_rounded, color: AppColors.roleDelivery),
              label: 'History',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline_rounded),
              selectedIcon: Icon(Icons.person_rounded, color: AppColors.roleDelivery),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}

class _MyRouteTab extends StatelessWidget {
  const _MyRouteTab();

  @override
  Widget build(BuildContext context) {
    // Filter to deliveries assigned to the current rider.
    final myDeliveries = activeDeliveries;
    final nextStop = myDeliveries.isEmpty
        ? null
        : myDeliveries.first.stops.firstWhere(
          (s) => s.status != StopStatus.delivered,
      orElse: () => myDeliveries.first.stops.last,
    );

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.roleDelivery,
            borderRadius: BorderRadius.circular(20),
            boxShadow: AppShadows.md,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('ROUTE ENGINE (SIMULATED)', style: AppTextStyles.labelSm.copyWith(color: Colors.white70)),
                  const Icon(Icons.gps_fixed_rounded, color: Colors.white, size: 18),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                myDeliveries.isEmpty ? 'No active route' : '${myDeliveries.first.stops.length} Drops Optimized',
                style: AppTextStyles.headlineSm.copyWith(color: Colors.white),
              ),
              Text(
                nextStop == null ? 'No pending stops' : 'Next stop: ${nextStop.customerName}',
                style: AppTextStyles.bodySm.copyWith(color: Colors.white70),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Active Assigned Deliveries', style: AppTextStyles.titleMd),
            TextButton(
              onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryAssigned),
              child: const Text('View All'),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        if (myDeliveries.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Text('No active deliveries assigned.', style: AppTextStyles.bodyMd),
          )
        else
          for (final delivery in myDeliveries)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _AssignedDeliveryCard(delivery: delivery),
            ),
      ],
    );
  }
}

class _AssignedDeliveryCard extends StatelessWidget {
  final Delivery delivery;

  const _AssignedDeliveryCard({required this.delivery});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(delivery.id, style: AppTextStyles.labelLg),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: delivery.status.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(delivery.status.label, style: AppTextStyles.labelSm.copyWith(color: delivery.status.color)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          for (int i = 0; i < delivery.stops.length; i++)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Row(
                children: [
                  Icon(
                    delivery.stops[i].status == StopStatus.delivered ? Icons.check_circle : Icons.radio_button_unchecked,
                    size: 16,
                    color: delivery.stops[i].status.color,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${i + 1}. ${delivery.stops[i].customerName}',
                      style: AppTextStyles.bodyMd,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
          const Divider(height: 24),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () {
                    final notStarted = delivery.stops.every((s) => s.status == StopStatus.pending);
                    if (notStarted) {
                      Navigator.of(context).pushNamed(AppRoutes.deliveryRoute, arguments: delivery);
                    } else {
                      Navigator.of(context).pushNamed(AppRoutes.gpsTracking, arguments: delivery);
                    }
                  },
                  icon: const Icon(Icons.navigation_rounded, size: 18),
                  label: const Text('Navigate'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryDetails, arguments: delivery),
                  icon: const Icon(Icons.checklist_rounded, size: 18),
                  label: const Text('Manage Stops'),
                  style: ElevatedButton.styleFrom(backgroundColor: AppColors.success),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RiderHistoryTab extends StatelessWidget {
  const _RiderHistoryTab();

  @override
  Widget build(BuildContext context) {
    final completed = pastDeliveries;

    if (completed.isEmpty) {
      return Center(child: Text('No completed deliveries yet.', style: AppTextStyles.bodyMd));
    }

    return ListView.separated(
      padding: const EdgeInsets.all(AppSpacing.md),
      itemCount: completed.length,
      separatorBuilder: (context, index) => const SizedBox(height: 12),
      itemBuilder: (context, i) {
        final delivery = completed[i];
        return ListTile(
          onTap: () => Navigator.of(context).pushNamed(AppRoutes.deliveryDetails, arguments: delivery),
          tileColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: const BorderSide(color: AppColors.border)),
          leading: const Icon(Icons.history_rounded, color: AppColors.textSecondary),
          title: Text(delivery.id, style: AppTextStyles.labelLg),
          subtitle: Text('${delivery.stops.length} stops • ${delivery.branch}', style: AppTextStyles.bodySm),
          trailing: const Icon(Icons.chevron_right_rounded),
        );
      },
    );
  }
}