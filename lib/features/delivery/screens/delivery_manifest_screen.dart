import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../core/widgets/primary_button.dart';
import '../../../data/models/delivery.dart';

/// Packing manifest for a dispatch — every stop's order items grouped for
/// the loading/packing crew, with a "Mark as Packed" simulation and a
/// dummy export action.
class DeliveryManifestScreen extends StatefulWidget {
  final Delivery delivery;

  const DeliveryManifestScreen({super.key, required this.delivery});

  @override
  State<DeliveryManifestScreen> createState() => _DeliveryManifestScreenState();
}

class _DeliveryManifestScreenState extends State<DeliveryManifestScreen> {
  bool _packed = false;

  @override
  Widget build(BuildContext context) {
    final delivery = widget.delivery;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: MelaiAppBar(
        title: 'Manifest • ${delivery.id}',
        showBack: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.print_outlined),
            tooltip: 'Export / Print',
            onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Simulated: manifest exported as PDF.')),
            ),
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
              child: Row(
                children: [
                  Expanded(child: _Summary(label: 'Branch', value: delivery.branch)),
                  Expanded(child: _Summary(label: 'Vehicle', value: delivery.vehicle)),
                ],
              ),
            ),
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border), boxShadow: AppShadows.sm),
              child: Row(
                children: [
                  Expanded(child: _Summary(label: 'Total Stops', value: '${delivery.stops.length}')),
                  Expanded(child: _Summary(label: 'Total Items', value: '${delivery.totalItems}')),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text('Packing List by Stop', style: AppTextStyles.headlineSm),
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
                            width: 24,
                            height: 24,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(color: AppColors.primaryContainer, shape: BoxShape.circle),
                            child: Text('${i + 1}', style: AppTextStyles.labelSm.copyWith(color: AppColors.primaryDark)),
                          ),
                          const SizedBox(width: 8),
                          Expanded(child: Text(delivery.stops[i].customerName, style: AppTextStyles.labelLg)),
                          Text(delivery.stops[i].orderId, style: AppTextStyles.bodySm),
                        ],
                      ),
                      const Divider(height: 16),
                      for (final item in delivery.stops[i].items)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 2),
                          child: Row(
                            children: [
                              const Icon(Icons.circle, size: 6, color: AppColors.textSecondary),
                              const SizedBox(width: 8),
                              Expanded(child: Text(item, style: AppTextStyles.bodyMd)),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: AppSpacing.md),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppColors.surfaceContainerLow, borderRadius: BorderRadius.circular(AppSpacing.radiusMd)),
              child: SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _packed,
                activeThumbColor: AppColors.success,
                title: Text('All items packed & verified', style: AppTextStyles.labelLg),
                subtitle: Text('Simulated packing checklist — nothing is sent to a backend.', style: AppTextStyles.bodySm),
                onChanged: (v) => setState(() => _packed = v),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            PrimaryButton(
              label: _packed ? 'Manifest Verified' : 'Confirm Manifest is Packed',
              icon: _packed ? Icons.check_circle_outline_rounded : Icons.inventory_2_outlined,
              onPressed: _packed
                  ? null
                  : () => setState(() => _packed = true),
            ),
          ],
        ),
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  final String label;
  final String value;

  const _Summary({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: AppTextStyles.bodySm),
        Text(value, style: AppTextStyles.titleMd),
      ],
    );
  }
}
