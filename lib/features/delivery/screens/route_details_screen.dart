import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../data/models/delivery.dart';

/// Deeper, per-leg breakdown of the route with simulated turn-by-turn
/// style directions for each leg.
class RouteDetailsScreen extends StatelessWidget {
  final Delivery delivery;

  const RouteDetailsScreen({super.key, required this.delivery});

  static const List<String> _defaultDirectionTemplates = [
    'Head out from {from} via the main access road.',
    'Continue straight for most of the leg, staying in the right lane.',
    'Turn onto the local barangay road toward {to}.',
    'Arrive at {to} — destination on the right.',
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: MelaiAppBar(title: 'Route Details • ${delivery.id}', showBack: true),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(color: AppColors.surfaceContainerLow, borderRadius: BorderRadius.circular(20), border: Border.all(color: AppColors.border)),
              child: Row(
                children: [
                  const Icon(Icons.info_outline_rounded, size: 16, color: AppColors.textSecondary),
                  const SizedBox(width: 6),
                  Expanded(child: Text('Simulated turn-by-turn — no real directions/routing API is connected.', style: AppTextStyles.bodySm)),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            for (int i = 0; i < delivery.stops.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: _LegCard(
                  legNumber: i + 1,
                  from: i == 0 ? delivery.branch : delivery.stops[i - 1].customerName,
                  to: delivery.stops[i].customerName,
                  distanceKm: delivery.stops[i].distanceFromPreviousKm,
                  minutes: delivery.stops[i].travelMinutesFromPrevious,
                  eta: delivery.stops[i].eta,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _LegCard extends StatelessWidget {
  final int legNumber;
  final String from;
  final String to;
  final double distanceKm;
  final int minutes;
  final String eta;

  const _LegCard({
    required this.legNumber,
    required this.from,
    required this.to,
    required this.distanceKm,
    required this.minutes,
    required this.eta,
  });

  @override
  Widget build(BuildContext context) {
    final steps = RouteDetailsScreen._defaultDirectionTemplates
        .map((t) => t.replaceAll('{from}', from).replaceAll('{to}', to))
        .toList();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: AppColors.primaryContainer, borderRadius: BorderRadius.circular(20)),
                child: Text('LEG $legNumber', style: AppTextStyles.labelSm.copyWith(color: AppColors.primaryDark)),
              ),
              const Spacer(),
              Text('${distanceKm.toStringAsFixed(1)} km • $minutes min', style: AppTextStyles.bodySm),
            ],
          ),
          const SizedBox(height: 6),
          Text('$from → $to', style: AppTextStyles.titleMd),
          Text('ETA $eta', style: AppTextStyles.bodySm.copyWith(color: AppColors.primary)),
          const Divider(height: 20),
          for (final step in steps)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.turn_right_rounded, size: 16, color: AppColors.textSecondary),
                  const SizedBox(width: 8),
                  Expanded(child: Text(step, style: AppTextStyles.bodyMd)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
