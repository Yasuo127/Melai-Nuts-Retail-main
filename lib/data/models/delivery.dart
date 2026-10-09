import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

/// Lifecycle status of an entire delivery dispatch.
enum DeliveryStatus { pending, optimized, dispatched, inTransit, completed, cancelled }

extension DeliveryStatusX on DeliveryStatus {
  String get label {
    switch (this) {
      case DeliveryStatus.pending:
        return 'Pending';
      case DeliveryStatus.optimized:
        return 'Route Optimized';
      case DeliveryStatus.dispatched:
        return 'Dispatched';
      case DeliveryStatus.inTransit:
        return 'In Transit';
      case DeliveryStatus.completed:
        return 'Completed';
      case DeliveryStatus.cancelled:
        return 'Cancelled';
    }
  }

  bool get isActive => this != DeliveryStatus.completed && this != DeliveryStatus.cancelled;

  Color get color {
    switch (this) {
      case DeliveryStatus.pending:
        return AppColors.textSecondary;
      case DeliveryStatus.optimized:
        return AppColors.warning;
      case DeliveryStatus.dispatched:
        return AppColors.primary;
      case DeliveryStatus.inTransit:
        return AppColors.success;
      case DeliveryStatus.completed:
        return AppColors.success;
      case DeliveryStatus.cancelled:
        return AppColors.error;
    }
  }
}

/// Status of a single stop within a delivery route.
enum StopStatus { pending, enRoute, delivered, delayed, skipped }

extension StopStatusX on StopStatus {
  String get label {
    switch (this) {
      case StopStatus.pending:
        return 'Pending';
      case StopStatus.enRoute:
        return 'En Route';
      case StopStatus.delivered:
        return 'Delivered';
      case StopStatus.delayed:
        return 'Delayed';
      case StopStatus.skipped:
        return 'Cancelled';
    }
  }

  Color get color {
    switch (this) {
      case StopStatus.pending:
        return AppColors.textSecondary;
      case StopStatus.enRoute:
        return AppColors.warning;
      case StopStatus.delivered:
        return AppColors.success;
      case StopStatus.delayed:
        return AppColors.warning;
      case StopStatus.skipped:
        return AppColors.error;
    }
  }
}

/// A single stop (customer drop-off) within a [Delivery] route.
class DeliveryStop {
  final String id;
  final String customerName;
  final String address;
  final String orderId;
  final List<String> items;

  /// Position in the *optimized* sequence (0 = first stop after branch).
  final int sequenceIndex;

  /// Distance/time from the previous stop (or from the branch, for the
  /// first stop) — simulated, not from a real routing engine.
  final double distanceFromPreviousKm;
  final int travelMinutesFromPrevious;
  final String eta;

  StopStatus status;
  String? issueReason; // populated when status is delayed/skipped
  String? proofNote; // delivery confirmation note (simulated proof)

  DeliveryStop({
    required this.id,
    required this.customerName,
    required this.address,
    required this.orderId,
    required this.items,
    required this.sequenceIndex,
    required this.distanceFromPreviousKm,
    required this.travelMinutesFromPrevious,
    required this.eta,
    this.status = StopStatus.pending,
    this.issueReason,
    this.proofNote,
  });

  /// Parses the jsonb shape returned by `staff_create_delivery` /
  /// `staff_get_deliveries` / `staff_update_delivery_stop`.
  factory DeliveryStop.fromJson(Map<String, dynamic> j) {
    return DeliveryStop(
      id: j['id'] as String,
      customerName: (j['customer_name'] as String?) ?? 'Customer',
      address: (j['address'] as String?) ?? 'Address not provided',
      orderId: j['order_id'] as String,
      items: ((j['items'] as List?) ?? const []).map((e) => e.toString()).toList(),
      sequenceIndex: (j['sequence_index'] as num).toInt(),
      distanceFromPreviousKm: (j['distance_from_previous_km'] as num?)?.toDouble() ?? 0,
      travelMinutesFromPrevious: (j['travel_minutes_from_previous'] as num?)?.toInt() ?? 0,
      eta: (j['eta'] as String?) ?? '',
      status: _stopStatusFromName(j['status'] as String?),
      issueReason: j['issue_reason'] as String?,
      proofNote: j['proof_note'] as String?,
    );
  }
}

StopStatus _stopStatusFromName(String? name) =>
    StopStatus.values.firstWhere((s) => s.name == name, orElse: () => StopStatus.pending);

DeliveryStatus _deliveryStatusFromName(String? name) =>
    DeliveryStatus.values.firstWhere((s) => s.name == name, orElse: () => DeliveryStatus.pending);

/// A delivery dispatch: one branch, one vehicle/rider, and an ordered list
/// of stops, backed by the real `deliveries`/`delivery_stops` tables (see
/// `DeliveryRepository`). There is still no real GPS, routing, or courier
/// API — per-stop distance/time/ETA are a simple, honestly-labeled estimate
/// computed server-side, not real routing.
class Delivery {
  final String id;
  final String branch;
  final String vehicle;
  final String riderName;
  final DateTime createdAt;
  final List<DeliveryStop> stops;
  DeliveryStatus status;

  Delivery({
    required this.id,
    required this.branch,
    required this.vehicle,
    required this.riderName,
    required this.createdAt,
    required this.stops,
    this.status = DeliveryStatus.pending,
  });

  /// Parses the jsonb shape returned by `staff_create_delivery` /
  /// `staff_get_deliveries` / `staff_update_delivery_stop`.
  factory Delivery.fromJson(Map<String, dynamic> j) {
    return Delivery(
      id: j['id'] as String,
      branch: (j['branch'] as String?) ?? '',
      vehicle: (j['vehicle'] as String?) ?? '',
      riderName: (j['rider_name'] as String?) ?? '',
      createdAt: DateTime.tryParse(j['created_at'] as String? ?? '') ?? DateTime.now(),
      status: _deliveryStatusFromName(j['status'] as String?),
      stops: ((j['stops'] as List?) ?? const [])
          .map((e) => DeliveryStop.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }

  double get totalDistanceKm => stops.fold(0, (sum, s) => sum + s.distanceFromPreviousKm);
  int get totalTimeMinutes => stops.fold(0, (sum, s) => sum + s.travelMinutesFromPrevious);
  int get totalItems => stops.fold(0, (sum, s) => sum + s.items.length);
  int get deliveredCount => stops.where((s) => s.status == StopStatus.delivered).length;
  bool get isComplete => stops.isNotEmpty && stops.every((s) => s.status == StopStatus.delivered);
}
