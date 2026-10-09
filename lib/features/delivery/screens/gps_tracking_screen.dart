import 'package:flutter/material.dart';
import '../../../app/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/primary_button.dart';
import '../../../data/models/delivery.dart';

/// Simulated GPS tracking.
class GpsTrackingScreen extends StatefulWidget {
  final Delivery delivery;

  const GpsTrackingScreen({super.key, required this.delivery});

  @override
  State<GpsTrackingScreen> createState() => _GpsTrackingScreenState();
}

class _GpsTrackingScreenState extends State<GpsTrackingScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    duration: const Duration(seconds: 6),
    vsync: this,
  )..repeat();

  DeliveryStop? get _nextStop => widget.delivery.stops.firstWhere(
        (s) => s.status == StopStatus.enRoute || s.status == StopStatus.pending,
        orElse: () => widget.delivery.stops.last,
      );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final delivery = widget.delivery;
    final nextStop = _nextStop;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('LIVE GPS (SIMULATED)', style: AppTextStyles.labelSm),
            Text('Delivery ${delivery.id}', style: AppTextStyles.titleMd),
          ],
        ),
      ),
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
                  Expanded(child: Text('Simulated GPS — no real location API is connected.', style: AppTextStyles.bodySm)),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Container(
              height: 260,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: AppColors.surfaceContainerLow, borderRadius: BorderRadius.circular(AppSpacing.radiusLg), border: Border.all(color: AppColors.border)),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final size = constraints.biggest;
                  final start = Offset(30, size.height - 30);
                  final end = Offset(size.width - 30, 30);
                  return AnimatedBuilder(
                    animation: _controller,
                    builder: (context, _) {
                      final t = _controller.value;
                      final vanPos = Offset.lerp(start, end, t)!;
                      return Stack(
                        children: [
                          Positioned.fill(child: CustomPaint(painter: _DashedLinePainter(start: start, end: end))),
                          Positioned(
                            left: start.dx - 16,
                            top: start.dy - 16,
                            child: const _Pin(icon: Icons.storefront_rounded, color: AppColors.roleOwner),
                          ),
                          Positioned(
                            left: end.dx - 16,
                            top: end.dy - 16,
                            child: _Pin(icon: Icons.flag_rounded, color: nextStop?.status.color ?? AppColors.success),
                          ),
                          Positioned(
                            left: vanPos.dx - 14,
                            top: vanPos.dy - 14,
                            child: Container(
                              width: 28,
                              height: 28,
                              decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
                              child: const Icon(Icons.local_shipping_rounded, size: 16, color: Colors.white),
                            ),
                          ),
                        ],
                      );
                    },
                  );
                },
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            if (nextStop != null)
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('HEADING TO', style: AppTextStyles.labelSm),
                    Text(nextStop.customerName, style: AppTextStyles.titleMd),
                    Text(nextStop.address, style: AppTextStyles.bodySm),
                    const Divider(height: 20),
                    Row(
                      children: [
                        Expanded(child: _Stat(label: 'Distance', value: '${nextStop.distanceFromPreviousKm.toStringAsFixed(1)} km')),
                        Expanded(child: _Stat(label: 'ETA', value: nextStop.eta)),
                        Expanded(child: _Stat(label: 'Speed (sim.)', value: '32 km/h')),
                      ],
                    ),
                  ],
                ),
              ),
            const SizedBox(height: AppSpacing.lg),
            PrimaryButton(
              label: 'View Next Stop',
              icon: Icons.arrow_forward_rounded,
              onPressed: () => Navigator.of(context).pushNamed(AppRoutes.deliveryNextStop, arguments: delivery),
            ),
          ],
        ),
      ),
    );
  }
}

class _Pin extends StatelessWidget {
  final IconData icon;
  final Color color;

  const _Pin({required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle, border: Border.all(color: color, width: 2)),
      child: Icon(icon, size: 16, color: color),
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

class _DashedLinePainter extends CustomPainter {
  final Offset start;
  final Offset end;

  _DashedLinePainter({required this.start, required this.end});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AppColors.primary.withValues(alpha: 0.5)
      ..strokeWidth = 3
      ..style = PaintingStyle.stroke;
    final path = Path()..moveTo(start.dx, start.dy)..lineTo(end.dx, end.dy);
    const dashWidth = 6.0;
    const dashSpace = 5.0;
    for (final metric in path.computeMetrics()) {
      double distance = 0;
      while (distance < metric.length) {
        canvas.drawPath(metric.extractPath(distance, distance + dashWidth), paint);
        distance += dashWidth + dashSpace;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedLinePainter oldDelegate) => false;
}
