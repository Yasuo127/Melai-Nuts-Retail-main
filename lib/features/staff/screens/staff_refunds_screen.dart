import 'package:flutter/material.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error.dart';
import '../../../core/utils/format_utils.dart';
import '../../../core/widgets/melai_app_bar.dart';
import '../../../core/widgets/staff_data_scope.dart';
import '../../../core/widgets/state_views.dart';
import '../../../data/models/staff_models.dart';
import '../../../data/repositories/staff_repository.dart';

/// Refund requests for this branch — only for staff the owner has allowed to
/// review refunds. The database refuses the list and every action otherwise,
/// so this screen shows nothing for anyone without the permission.
class StaffRefundsScreen extends StatelessWidget {
  const StaffRefundsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StaffDataScope(
      builder: (context, store) {
        if (!store.canReviewRefunds) {
          return Scaffold(
            backgroundColor: AppColors.canvas,
            appBar: const MelaiAppBar(title: 'Refund Requests', showBack: true),
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Text('You do not have permission to review refunds.', textAlign: TextAlign.center, style: AppTextStyles.bodyMd),
              ),
            ),
          );
        }
        // Loaded lazily: staff without the permission never request it.
        if (!store.refundsState.loaded && !store.refundsState.loading && store.refundsState.error == null) {
          WidgetsBinding.instance.addPostFrameCallback((_) => store.refreshRefunds());
        }
        final refunds = store.refunds;
        return Scaffold(
          backgroundColor: AppColors.canvas,
          appBar: const MelaiAppBar(title: 'Refund Requests', showBack: true),
          body: SafeArea(
            child: RefreshIndicator(
              onRefresh: store.refreshRefunds,
              child: DataStateView(
                isLoading: store.refundsState.busy,
                error: store.refundsState.error,
                isEmpty: refunds.isEmpty,
                onRetry: store.refreshRefunds,
                emptyIcon: Icons.assignment_return_outlined,
                emptyTitle: 'No refund requests.',
                builder: (context) => ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(AppSpacing.md),
                  itemCount: refunds.length,
                  separatorBuilder: (context, index) => const SizedBox(height: 10),
                  itemBuilder: (context, i) => _RefundCard(refund: refunds[i], store: store),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _RefundCard extends StatefulWidget {
  final StaffRefund refund;
  final StaffStore store;

  const _RefundCard({required this.refund, required this.store});

  @override
  State<_RefundCard> createState() => _RefundCardState();
}

class _RefundCardState extends State<_RefundCard> {
  bool _busy = false;

  Future<void> _act(String action, String done) async {
    setState(() => _busy = true);
    try {
      await StaffRepository.instance.reviewRefund(widget.refund.id, action);
      if (!mounted) return;
      widget.store.refreshLive();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(done)));
    } catch (e) {
      if (mounted) AppErrors.showSnack(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.refund;
    final buttons = <Widget>[];
    if (!_busy) {
      switch (r.status) {
        case 'pending':
          buttons.addAll([
            Expanded(child: FilledButton(onPressed: () => _act('approve', 'Refund approved.'), child: const Text('Approve'))),
            const SizedBox(width: 8),
            Expanded(child: OutlinedButton(onPressed: () => _act('reject', 'Refund rejected.'), child: const Text('Reject'))),
          ]);
          break;
        case 'approved':
          buttons.add(Expanded(child: FilledButton(onPressed: () => _act('process', 'Refund is being processed.'), child: const Text('Start processing'))));
          break;
        case 'processing':
          buttons.add(Expanded(child: FilledButton(onPressed: () => _act('complete', 'Refund completed.'), child: const Text('Mark refunded'))));
          break;
      }
    }

    return Container(
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
              Expanded(child: Text('${r.id} • ${r.orderId}', style: AppTextStyles.labelLg)),
              Text(r.status.toUpperCase(), style: AppTextStyles.labelSm),
            ],
          ),
          const SizedBox(height: 6),
          Text('${r.customerName ?? 'Customer'} • ${peso(r.amount)} via ${r.paymentMethod}', style: AppTextStyles.bodyMd),
          Text(friendlyTime(r.createdAt), style: AppTextStyles.bodySm),
          if (r.reason.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text('Reason: ${r.reason}', style: AppTextStyles.bodySm),
          ],
          if (r.notes.isNotEmpty) Text('Notes: ${r.notes}', style: AppTextStyles.bodySm),
          for (final i in r.items)
            Text('${i.quantity} × ${i.productName}${i.variantLabel.isEmpty ? '' : ' • ${i.variantLabel}'}', style: AppTextStyles.bodySm),
          if (_busy) ...[const SizedBox(height: 10), const LinearProgressIndicator()],
          if (buttons.isNotEmpty) ...[const SizedBox(height: 10), Row(children: buttons)],
        ],
      ),
    );
  }
}
