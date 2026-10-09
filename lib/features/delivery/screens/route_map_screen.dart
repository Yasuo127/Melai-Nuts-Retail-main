import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../data/models/delivery.dart';

/// A decorative, non-interactive route visualization — numbered stop pins
/// connected by a dashed route line over a static illustrative canvas.
///
/// This is explicitly NOT a real map: there is no Google Maps API or any
/// other mapping SDK wired in. It exists purely to visualize the stop
/// sequence and current delivery status.
class RouteMapScreen extends StatelessWidget {
  final Delivery delivery;

  const RouteMapScreen({super.key, required this.delivery});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: MelaiAppBar(title: 'Route Map • ${delivery.id}', showBack: true),
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
                  Expanded(
                    child: Text(
                      'Illustrative route only — no live GPS/Maps API connected in this frontend build.',
                      style: AppTextStyles.bodySm,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Container(
              height: 340,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
                border: Border.all(color: AppColors.border),
              ),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final points = _layoutPoints(delivery.stops.length + 1, constraints.biggest);
                  return Stack(
                    children: [
                      Positioned.fill(
                        child: CustomPaint(painter: _RoutePainter(points: points)),
                      ),
                      // Branch pin.
                      _MapPin(
                        position: points[0],
                        icon: Icons.storefront_rounded,
                        label: 'Branch',
                        color: AppColors.roleOwner,
                        filled: true,
                      ),
                      for (int i = 0; i < delivery.stops.length; i++)
                        _MapPin(
                          position: points[i + 1],
                          icon: Icons.location_on_rounded,
                          label: '${i + 1}',
                          color: delivery.stops[i].status.color,
                          filled: delivery.stops[i].status == StopStatus.delivered,
                        ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: 14,
              runSpacing: 6,
              children: [
                _Legend(color: AppColors.roleOwner, label: 'Branch'),
                _Legend(color: AppColors.success, label: 'Delivered'),
                _Legend(color: AppColors.warning, label: 'En Route'),
                _Legend(color: AppColors.textSecondary, label: 'Pending'),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            Text('Stops on This Route', style: AppTextStyles.headlineSm),
            const SizedBox(height: AppSpacing.sm),
            for (int i = 0; i < delivery.stops.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border)),
                  child: Row(
                    children: [
                      Container(
                        width: 26,
                        height: 26,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(color: delivery.stops[i].status.color.withValues(alpha: 0.15), shape: BoxShape.circle),
                        child: Text('${i + 1}', style: AppTextStyles.labelSm.copyWith(color: delivery.stops[i].status.color)),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(delivery.stops[i].customerName, style: AppTextStyles.labelLg, maxLines: 1, overflow: TextOverflow.ellipsis),
                            Text('${delivery.stops[i].distanceFromPreviousKm.toStringAsFixed(1)} km • ETA ${delivery.stops[i].eta}', style: AppTextStyles.bodySm),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  /// Lays out N points in a simple zig-zag path across the canvas — purely
  /// decorative, not based on real coordinates.
  List<Offset> _layoutPoints(int count, Size size) {
    final points = <Offset>[];
    final usableWidth = size.width - 60;
    final usableHeight = size.height - 60;
    for (int i = 0; i < count; i++) {
      final t = count <= 1 ? 0.0 : i / (count - 1);
      final x = 30 + usableWidth * t;
      final y = 30 + usableHeight * (i.isEven ? 0.15 + t * 0.5 : 0.55 + t * 0.3);
      points.add(Offset(x, y.clamp(30, size.height - 30)));
    }
    return points;
  }
}

class _RoutePainter extends CustomPainter {
  final List<Offset> points;

  _RoutePainter({required this.points});

  @override
  void paint(Canvas canvas, Size size) {
    if (points.length < 2) return;
    final paint = Paint()
      ..color = AppColors.primary
      ..strokeWidth = 3
      ..style = PaintingStyle.stroke;

    final path = Path()..moveTo(points.first.dx, points.first.dy);
    for (int i = 1; i < points.length; i++) {
      final prev = points[i - 1];
      final curr = points[i];
      final mid = Offset((prev.dx + curr.dx) / 2, (prev.dy + curr.dy) / 2);
      path.quadraticBezierTo(prev.dx, curr.dy, mid.dx, mid.dy);
      path.quadraticBezierTo(curr.dx, prev.dy, curr.dx, curr.dy);
    }

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
  bool shouldRepaint(covariant _RoutePainter oldDelegate) => oldDelegate.points != points;
}

class _MapPin extends StatelessWidget {
  final Offset position;
  final IconData icon;
  final String label;
  final Color color;
  final bool filled;

  const _MapPin({required this.position, required this.icon, required this.label, required this.color, this.filled = false});

  @override
  Widget build(BuildContext context) {
    return Positioned(
      left: position.dx - 16,
      top: position.dy - 16,
      child: Column(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: filled ? color : Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: color, width: 2),
              boxShadow: AppShadows.sm,
            ),
            child: Icon(icon, size: 16, color: filled ? Colors.white : color),
          ),
        ],
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  final Color color;
  final String label;

  const _Legend({required this.color, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 6),
        Text(label, style: AppTextStyles.bodySm),
      ],
    );
  }
}
