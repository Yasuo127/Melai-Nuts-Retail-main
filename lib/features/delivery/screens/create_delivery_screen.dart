import 'package:flutter/material.dart';
import '../../../app/routes.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error.dart';
import '../../../core/utils/validation_utils.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../core/widgets/primary_button.dart';
import '../../../core/widgets/staff_data_scope.dart';
import '../../../core/widgets/state_views.dart';
import '../../../data/repositories/delivery_repository.dart';

/// "Create Delivery" — pick which real, delivery-ready orders to dispatch
/// together, plus a vehicle and rider, then create the delivery for real.
///
/// There is no geocoding/routing API available to this app, so the per-stop
/// distance/time/ETA the backend computes is a simple, honestly-labeled
/// estimate (see `staff_create_delivery` in the delivery module migration) —
/// not a real routing engine. Orders are added in the order the staff member
/// picked them; that becomes the stop sequence.
class CreateDeliveryScreen extends StatelessWidget {
  const CreateDeliveryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StaffDataScope(builder: (context, store) => _CreateDeliveryBody(store: store));
  }
}

class _CreateDeliveryBody extends StatefulWidget {
  final StaffStore store;
  const _CreateDeliveryBody({required this.store});

  @override
  State<_CreateDeliveryBody> createState() => _CreateDeliveryBodyState();
}

class _CreateDeliveryBodyState extends State<_CreateDeliveryBody> {
  final _formKey = GlobalKey<FormState>();
  final _vehicleController = TextEditingController();
  final _riderController = TextEditingController();

  bool _loadingOrders = true;
  Object? _loadError;
  List<DeliverableOrder> _orders = const [];

  /// Picked order ids, in the order the staff member tapped them — that
  /// order becomes the stop sequence.
  final List<String> _selectedOrderIds = [];

  bool _creating = false;

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  @override
  void dispose() {
    _vehicleController.dispose();
    _riderController.dispose();
    super.dispose();
  }

  Future<void> _loadOrders() async {
    final branchId = widget.store.activeBranchId;
    if (branchId == null) {
      setState(() {
        _loadingOrders = false;
        _loadError = const AppError(AppErrorKind.notFound, 'Please choose a branch first.');
      });
      return;
    }
    setState(() {
      _loadingOrders = true;
      _loadError = null;
    });
    try {
      final orders = await DeliveryRepository.instance.listDeliverableOrders(branchId);
      if (!mounted) return;
      setState(() {
        _orders = orders;
        _selectedOrderIds.removeWhere((id) => orders.every((o) => o.orderId != id));
        _loadingOrders = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loadError = e;
        _loadingOrders = false;
      });
    }
  }

  void _toggle(String orderId) {
    setState(() {
      if (_selectedOrderIds.contains(orderId)) {
        _selectedOrderIds.remove(orderId);
      } else {
        _selectedOrderIds.add(orderId);
      }
    });
  }

  Future<void> _create() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedOrderIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one order for this delivery.')),
      );
      return;
    }
    final branchId = widget.store.activeBranchId;
    if (branchId == null) return;

    setState(() => _creating = true);
    try {
      final delivery = await DeliveryRepository.instance.createDelivery(
        branchId: branchId,
        vehicle: _vehicleController.text.trim(),
        riderName: _riderController.text.trim(),
        orderIds: _selectedOrderIds,
      );
      widget.store.applyDelivery(delivery);
      if (!mounted) return;
      Navigator.of(context).pushReplacementNamed(AppRoutes.routeOptimization, arguments: delivery);
    } catch (e) {
      if (!mounted) return;
      setState(() => _creating = false);
      AppErrors.showSnack(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: const MelaiAppBar(title: 'Create Delivery', showBack: true),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: AppColors.surfaceContainerLow, borderRadius: BorderRadius.circular(AppSpacing.radiusMd), border: Border.all(color: AppColors.border)),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline_rounded, color: AppColors.primary),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Stop distance/time is a simple estimate — there is no real routing engine behind this.',
                        style: AppTextStyles.bodyMd,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Text('Dispatch From', style: AppTextStyles.headlineSm),
              const SizedBox(height: 4),
              Text(
                widget.store.activeBranchName.isEmpty ? 'No branch selected' : widget.store.activeBranchName,
                style: AppTextStyles.bodyMd,
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  Expanded(
                    child: AppTextField(
                      label: 'Vehicle',
                      controller: _vehicleController,
                      prefixIcon: Icons.local_shipping_outlined,
                      hint: 'e.g. Laguna Van #04',
                      validator: (v) => ValidationUtils.validateRequired(v, 'Vehicle'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              AppTextField(
                label: 'Rider Name',
                controller: _riderController,
                prefixIcon: Icons.badge_outlined,
                hint: 'e.g. Juan dela Cruz',
                validator: (v) => ValidationUtils.validateName(v, 'Rider name'),
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Orders to Deliver', style: AppTextStyles.headlineSm),
                  Text('${_selectedOrderIds.length} selected', style: AppTextStyles.bodySm),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              if (_loadingOrders)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: StateLoadingView(message: 'Loading deliverable orders…', compact: true),
                )
              else if (_loadError != null)
                StateErrorView(error: _loadError, onRetry: _loadOrders, compact: true)
              else if (_orders.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Text(
                    'No confirmed delivery orders are waiting at this branch right now.',
                    style: AppTextStyles.bodyMd,
                  ),
                )
              else
                for (final order in _orders)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _OrderTile(
                      order: order,
                      selected: _selectedOrderIds.contains(order.orderId),
                      sequenceNumber: _selectedOrderIds.indexOf(order.orderId) + 1,
                      onTap: () => _toggle(order.orderId),
                    ),
                  ),
              const SizedBox(height: AppSpacing.lg),
              PrimaryButton(
                label: 'Create Delivery',
                icon: Icons.add_road_rounded,
                loading: _creating,
                onPressed: _creating ? null : _create,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OrderTile extends StatelessWidget {
  final DeliverableOrder order;
  final bool selected;
  final int sequenceNumber;
  final VoidCallback onTap;

  const _OrderTile({
    required this.order,
    required this.selected,
    required this.sequenceNumber,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryContainer.withValues(alpha: 0.3) : Colors.white,
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          border: Border.all(color: selected ? AppColors.primary : AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 26,
              height: 26,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: selected ? AppColors.primary : AppColors.surfaceContainerLow,
                shape: BoxShape.circle,
              ),
              child: selected
                  ? Text('$sequenceNumber', style: AppTextStyles.labelMd.copyWith(color: Colors.white))
                  : const Icon(Icons.receipt_long_outlined, size: 14, color: AppColors.textSecondary),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(order.customerName, style: AppTextStyles.labelLg)),
                      Text(order.orderId, style: AppTextStyles.bodySm),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(order.address, style: AppTextStyles.bodySm, maxLines: 2, overflow: TextOverflow.ellipsis),
                  if (order.items.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(order.items.join(', '), style: AppTextStyles.bodySm, maxLines: 1, overflow: TextOverflow.ellipsis),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
