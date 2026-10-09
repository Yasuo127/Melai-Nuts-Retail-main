import 'package:flutter/material.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error.dart';
import '../../../core/utils/format_utils.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../core/widgets/primary_button.dart';
import '../../../data/models/order.dart';
import '../../../data/models/staff_models.dart';
import '../../../data/repositories/staff_repository.dart';

/// Order / sale details with the actions staff may take.
///
/// The screen shows the latest copy of the order from [StaffStore] (so it
/// updates live), falling back to the one it was opened with. Every action is
/// executed and validated by the database — for instance an order cannot be
/// completed until its payment is confirmed.
class TransactionDetailsScreen extends StatefulWidget {
  final StaffOrder order;

  const TransactionDetailsScreen({super.key, required this.order});

  @override
  State<TransactionDetailsScreen> createState() => _TransactionDetailsScreenState();
}

class _TransactionDetailsScreenState extends State<TransactionDetailsScreen> {
  bool _busy = false;

  StaffOrder _latest(StaffStore store) {
    for (final o in store.orders) {
      if (o.id == widget.order.id) return o;
    }
    return widget.order;
  }

  Future<void> _run(Future<void> Function() action, String done) async {
    setState(() => _busy = true);
    try {
      await action();
      await StaffStore.instance.refreshLive();
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(done)));
    } catch (e) {
      if (mounted) AppErrors.showSnack(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _setStatus(StaffOrder o, String status, String done) =>
      _run(() => StaffRepository.instance.updateOrderStatus(o.id, status), done);

  Future<void> _confirmPayment(StaffOrder o) async {
    final method = o.payment?.method ?? '';
    String? ref;
    if (method != 'cash') {
      ref = await showDialog<String>(
        context: context,
        builder: (ctx) => const _PaymentReferenceDialog(),
      );
      if (ref == null) return;
    } else {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Confirm cash received'),
          content: Text('Confirm that ${peso(o.total)} in cash was received for ${o.id}?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Yes, received')),
          ],
        ),
      );
      if (ok != true) return;
    }
    await _run(() => StaffRepository.instance.confirmPayment(o.id, reference: ref), 'Payment confirmed.');
  }

  Future<void> _cancel(StaffOrder o) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel this order?'),
        content: const Text('Stock will be returned to the branch. This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep order')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.error),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Cancel order'),
          ),
        ],
      ),
    );
    if (ok == true) await _setStatus(o, 'cancelled', 'Order cancelled.');
  }

  List<Widget> _actions(StaffOrder o) {
    final paid = o.isPaid;
    final widgets = <Widget>[];
    void add(Widget w) {
      if (widgets.isNotEmpty) widgets.add(const SizedBox(height: 10));
      widgets.add(w);
    }

    switch (o.status) {
      case OrderStatus.pending:
        add(PrimaryButton(label: 'Accept Order', icon: Icons.check_rounded, loading: _busy, onPressed: () => _setStatus(o, 'confirmed', 'Order accepted.')));
        break;
      case OrderStatus.confirmed:
        add(PrimaryButton(label: 'Start Preparing', icon: Icons.inventory_rounded, loading: _busy, onPressed: () => _setStatus(o, 'preparing', 'Order is being prepared.')));
        break;
      case OrderStatus.preparing:
        if (o.isDelivery) {
          add(PrimaryButton(label: 'Out for Delivery', icon: Icons.local_shipping_outlined, loading: _busy, onPressed: () => _setStatus(o, 'outForDelivery', 'Order is out for delivery.')));
        } else {
          add(PrimaryButton(label: 'Ready for Pickup', icon: Icons.shopping_bag_outlined, loading: _busy, onPressed: () => _setStatus(o, 'readyForPickup', 'Order is ready for pickup.')));
        }
        break;
      case OrderStatus.readyForPickup:
      case OrderStatus.outForDelivery:
        if (paid) {
          add(PrimaryButton(label: 'Mark Completed', icon: Icons.done_all_rounded, loading: _busy, onPressed: () => _setStatus(o, 'completed', 'Order completed.')));
        }
        break;
      default:
        break;
    }

    final open = o.status.isActive;
    final payment = o.payment;
    if (open && payment != null && (payment.status == 'pending' || payment.status == 'processing')) {
      add(OutlinedButton.icon(
        onPressed: _busy ? null : () => _confirmPayment(o),
        icon: const Icon(Icons.payments_outlined),
        label: Text(payment.method == 'cash' ? 'Confirm Cash Received' : 'Confirm Payment'),
      ));
    }
    if (open && o.status != OrderStatus.outForDelivery) {
      add(TextButton.icon(
        onPressed: _busy ? null : () => _cancel(o),
        icon: const Icon(Icons.cancel_outlined, color: AppColors.error),
        label: const Text('Cancel Order', style: TextStyle(color: AppColors.error)),
      ));
    }
    return widgets;
  }

  @override
  Widget build(BuildContext context) {
    final store = StaffStore.instance;
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final o = _latest(store);
        final actions = _actions(o);
        return Scaffold(
          backgroundColor: AppColors.canvas,
          appBar: MelaiAppBar(title: o.id, showBack: true),
          body: SafeArea(
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.md),
              children: [
                _card([
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Status', style: AppTextStyles.bodyMd),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: o.status.color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
                        child: Text(o.status.label, style: AppTextStyles.labelMd.copyWith(color: o.status.color)),
                      ),
                    ],
                  ),
                  _row('Placed', friendlyTime(o.createdAt)),
                  _row('Channel', o.channelLabel),
                  _row('Customer', o.customerLabel),
                  if (o.contactPhone.isNotEmpty) _row('Phone', o.contactPhone),
                  if (o.isDelivery && (o.deliveryAddressText ?? '').isNotEmpty) _row('Deliver to', o.deliveryAddressText!),
                  if (o.isPos && (o.cashierName ?? '').isNotEmpty) _row('Cashier', o.cashierName!),
                  if (o.customerNotes.isNotEmpty) _row('Notes', o.customerNotes),
                ]),
                const SizedBox(height: AppSpacing.md),
                _card([
                  Text('Items', style: AppTextStyles.labelLg),
                  const SizedBox(height: 8),
                  for (final i in o.items)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${i.quantity} × ${i.productName}${i.variantLabel.isEmpty ? '' : ' • ${i.variantLabel}'}',
                              style: AppTextStyles.bodyMd,
                            ),
                          ),
                          Text(peso(i.total), style: AppTextStyles.bodyMd),
                        ],
                      ),
                    ),
                  const Divider(height: 24),
                  _row('Subtotal', peso(o.subtotal)),
                  if (o.discount > 0) _row('Discount', '-${peso(o.discount)}'),
                  if (o.deliveryFee > 0) _row('Delivery fee', peso(o.deliveryFee)),
                  _row('Total', peso(o.total), bold: true),
                ]),
                const SizedBox(height: AppSpacing.md),
                _card([
                  Text('Payment', style: AppTextStyles.labelLg),
                  const SizedBox(height: 8),
                  _row('Method', o.paymentMethod),
                  _row('Status', o.isPaid ? 'Paid' : (o.payment?.status ?? 'Pending')),
                  if ((o.payment?.referenceNumber ?? '').isNotEmpty && o.payment!.method != 'cash')
                    _row('Reference', o.payment!.referenceNumber),
                  if (o.cashReceived != null) _row('Cash received', peso(o.cashReceived!)),
                  if (o.changeGiven != null) _row('Change given', peso(o.changeGiven!)),
                  if (o.pointsEarned > 0) _row('Loyalty points', '+${o.pointsEarned}'),
                ]),
                if (actions.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.lg),
                  ...actions,
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _card(List<Widget> children) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
          border: Border.all(color: AppColors.border),
          boxShadow: AppShadows.sm,
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
      );

  Widget _row(String label, String value, {bool bold = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: AppTextStyles.bodyMd),
            const SizedBox(width: 16),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: bold ? AppTextStyles.labelLg : AppTextStyles.bodyMd,
              ),
            ),
          ],
        ),
      );
}


/// Owns its own [TextEditingController] so it is disposed together with the
/// dialog (after the close animation) rather than while the field is still
/// on screen.
class _PaymentReferenceDialog extends StatefulWidget {
  const _PaymentReferenceDialog();

  @override
  State<_PaymentReferenceDialog> createState() => _PaymentReferenceDialogState();
}

class _PaymentReferenceDialogState extends State<_PaymentReferenceDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Confirm payment'),
      content: TextField(
        controller: _controller,
        autofocus: true,
        decoration: const InputDecoration(labelText: 'Payment reference number'),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: () => Navigator.pop(context, _controller.text.trim()),
          child: const Text('Confirm'),
        ),
      ],
    );
  }
}
