import 'dart:async';

import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/services/supabase_service.dart';
import '../../core/utils/app_error.dart';
import '../models/delivery.dart';

/// One order eligible to become a delivery stop (`staff_list_deliverable_orders`).
class DeliverableOrder {
  final String orderId;
  final String customerName;
  final String address;
  final List<String> items;

  const DeliverableOrder({
    required this.orderId,
    required this.customerName,
    required this.address,
    required this.items,
  });

  factory DeliverableOrder.fromJson(Map<String, dynamic> j) => DeliverableOrder(
        orderId: j['order_id'] as String,
        customerName: (j['customer_name'] as String?) ?? 'Customer',
        address: (j['address'] as String?) ?? 'Address not provided',
        items: ((j['items'] as List?) ?? const []).map((e) => e.toString()).toList(),
      );
}

/// Every staff-side call to the Delivery module backend.
///
/// Mirrors [StaffRepository]'s style: everything goes through `staff_*`
/// database functions, which enforce branch access on the server.
class DeliveryRepository {
  DeliveryRepository._();
  static final DeliveryRepository instance = DeliveryRepository._();

  SupabaseClient get _client => SupabaseService.instance.client;

  static const Duration _timeout = Duration(seconds: 30);

  Future<dynamic> _rpc(String fn, [Map<String, dynamic>? params]) async {
    try {
      return await _client.rpc(fn, params: params).timeout(_timeout);
    } on PostgrestException catch (e) {
      if (e.code == 'P0001' && e.message.trim().isNotEmpty) {
        throw AppError(AppErrorKind.unknown, e.message.trim());
      }
      throw AppErrors.from(e);
    } catch (e) {
      throw AppErrors.from(e);
    }
  }

  Map<String, dynamic> _map(dynamic v) => Map<String, dynamic>.from(v as Map);
  List<Map<String, dynamic>> _list(dynamic v) =>
      ((v as List?) ?? const []).map((e) => Map<String, dynamic>.from(e as Map)).toList();

  /// Real orders at [branchId] that can be added as stops to a new delivery.
  Future<List<DeliverableOrder>> listDeliverableOrders(String? branchId) async =>
      _list(await _rpc('staff_list_deliverable_orders', {'p_branch_id': branchId}))
          .map(DeliverableOrder.fromJson)
          .toList();

  /// Creates a real delivery from a chosen set of orders (in the given,
  /// already-decided stop order) and returns the full, populated [Delivery].
  Future<Delivery> createDelivery({
    required String branchId,
    required String vehicle,
    required String riderName,
    required List<String> orderIds,
  }) async =>
      Delivery.fromJson(_map(await _rpc('staff_create_delivery', {
        'p_branch_id': branchId,
        'p_vehicle': vehicle,
        'p_rider_name': riderName,
        'p_order_ids': orderIds,
      })));

  /// Every delivery the caller may see (their branch, or every branch for
  /// an owner), newest first.
  Future<List<Delivery>> getDeliveries(String? branchId) async =>
      _list(await _rpc('staff_get_deliveries', {'p_branch_id': branchId}))
          .map(Delivery.fromJson)
          .toList();

  /// Marks a delivery as dispatched (handed off to the rider) before any
  /// stop has moved. A stop update advances status past this automatically.
  Future<Delivery> dispatchDelivery(String deliveryId) async =>
      Delivery.fromJson(_map(await _rpc('staff_dispatch_delivery', {'p_delivery_id': deliveryId})));

  /// Updates one stop's status. [status] is a [StopStatus] name
  /// ('pending' | 'enRoute' | 'delivered' | 'delayed' | 'skipped').
  /// Marking a stop 'delivered' also completes its order server-side; the
  /// parent delivery's own status is rolled forward automatically.
  Future<Delivery> updateDeliveryStop({
    required String stopId,
    required String status,
    String? issueReason,
    String? proofNote,
  }) async =>
      Delivery.fromJson(_map(await _rpc('staff_update_delivery_stop', {
        'p_stop_id': stopId,
        'p_status': status,
        'p_issue_reason': issueReason,
        'p_proof_note': proofNote,
      })));

  /// Cancels a whole delivery; its still-pending orders return to
  /// 'confirmed' so they can be redispatched.
  Future<Delivery> cancelDelivery(String deliveryId) async =>
      Delivery.fromJson(_map(await _rpc('staff_cancel_delivery', {'p_delivery_id': deliveryId})));
}
