import 'order.dart';

double _num(dynamic v) => (v as num?)?.toDouble() ?? 0;
int _int(dynamic v) => (v as num?)?.toInt() ?? 0;
DateTime _time(dynamic v) => DateTime.parse(v as String).toLocal();

/// The signed-in staff member as recorded in the database's own staff
/// registry (`staff_members`). This — not anything cached on the device — is
/// what the database checks on every staff request.
///
/// Identity is [uid] (the Firebase UID). [email] is a contact/display field
/// kept in step with the Firebase account by the server
/// (`staff_sync_my_identity`); it is never used to decide who someone is.
class StaffProfile {
  final String uid;
  final String fullName;
  final String email;
  final String? phone;
  final String? profileImage;
  final String role; // 'staff' | 'owner'
  final String? branchId;
  final String? branchName;

  /// 'active' | 'inactive' | 'suspended'. Only 'active' ever reaches the app
  /// as a usable profile; the others are reported as session statuses.
  final String accountStatus;
  final bool isActive;
  final bool canManageInventory;
  final bool canReviewRefunds;
  final DateTime? createdAt;

  const StaffProfile({
    required this.uid,
    required this.fullName,
    required this.email,
    required this.role,
    required this.branchId,
    required this.branchName,
    required this.isActive,
    required this.canManageInventory,
    required this.canReviewRefunds,
    required this.createdAt,
    this.phone,
    this.profileImage,
    this.accountStatus = 'active',
  });

  bool get isOwner => role == 'owner';
  String get roleLabel => isOwner ? 'Owner' : 'Branch Staff';

  String get initials {
    final parts = fullName.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  factory StaffProfile.fromJson(Map<String, dynamic> j) {
    final isActive = (j['is_active'] as bool?) ?? false;
    return StaffProfile(
      uid: j['firebase_uid'] as String,
      fullName: (j['full_name'] as String?) ?? '',
      email: (j['email'] as String?) ?? '',
      phone: j['phone'] as String?,
      profileImage: j['profile_image'] as String?,
      role: (j['role'] as String?) ?? 'staff',
      branchId: j['branch_id'] as String?,
      branchName: j['branch_name'] as String?,
      accountStatus: (j['account_status'] as String?) ?? (isActive ? 'active' : 'inactive'),
      isActive: isActive,
      canManageInventory: (j['can_manage_inventory'] as bool?) ?? false,
      canReviewRefunds: (j['can_review_refunds'] as bool?) ?? false,
      createdAt: j['created_at'] == null ? null : _time(j['created_at']),
    );
  }

  /// Same shape [StaffProfile.fromJson] reads (used for the offline cache).
  Map<String, dynamic> toJson() => {
        'firebase_uid': uid,
        'full_name': fullName,
        'email': email,
        'phone': phone,
        'profile_image': profileImage,
        'role': role,
        'branch_id': branchId,
        'branch_name': branchName,
        'account_status': accountStatus,
        'is_active': isActive,
        'can_manage_inventory': canManageInventory,
        'can_review_refunds': canReviewRefunds,
        'created_at': createdAt?.toUtc().toIso8601String(),
      };
}

class StaffBranchInfo {
  final String id;
  final String name;
  final String address;
  final String? contactPhone;
  final String? operatingHours;
  final bool isActive;
  final bool supportsDelivery;
  final bool supportsPickup;

  const StaffBranchInfo({
    required this.id,
    required this.name,
    required this.address,
    required this.contactPhone,
    required this.operatingHours,
    required this.isActive,
    required this.supportsDelivery,
    required this.supportsPickup,
  });

  factory StaffBranchInfo.fromJson(Map<String, dynamic> j) => StaffBranchInfo(
        id: j['id'] as String,
        name: (j['name'] as String?) ?? '',
        address: (j['address'] as String?) ?? '',
        contactPhone: j['contact_phone'] as String?,
        operatingHours: j['operating_hours'] as String?,
        isActive: (j['is_active'] as bool?) ?? true,
        supportsDelivery: (j['supports_delivery'] as bool?) ?? false,
        supportsPickup: (j['supports_pickup'] as bool?) ?? false,
      );
}

/// Live numbers for the staff dashboard, computed by the database.
class StaffDashboard {
  final StaffBranchInfo branch;
  final double salesToday;
  final int ordersToday;
  final int pendingOrders;
  final int preparingOrders;
  final int readyOrders;
  final int completedToday;
  final int cancelledToday;
  final int lowStockCount;
  final int outOfStockCount;
  final int expiringSoonCount;
  final int transfersAwaitingApproval;
  final int transfersAwaitingReceipt;

  /// Null when this staff member is not allowed to review refunds.
  final int? pendingRefunds;
  final int unreadNotifications;
  final DateTime generatedAt;

  const StaffDashboard({
    required this.branch,
    required this.salesToday,
    required this.ordersToday,
    required this.pendingOrders,
    required this.preparingOrders,
    required this.readyOrders,
    required this.completedToday,
    required this.cancelledToday,
    required this.lowStockCount,
    required this.outOfStockCount,
    required this.expiringSoonCount,
    required this.transfersAwaitingApproval,
    required this.transfersAwaitingReceipt,
    required this.pendingRefunds,
    required this.unreadNotifications,
    required this.generatedAt,
  });

  factory StaffDashboard.fromJson(Map<String, dynamic> j) => StaffDashboard(
        branch: StaffBranchInfo.fromJson(Map<String, dynamic>.from(j['branch'] as Map)),
        salesToday: _num(j['sales_today']),
        ordersToday: _int(j['orders_today']),
        pendingOrders: _int(j['pending_orders']),
        preparingOrders: _int(j['preparing_orders']),
        readyOrders: _int(j['ready_orders']),
        completedToday: _int(j['completed_today']),
        cancelledToday: _int(j['cancelled_today']),
        lowStockCount: _int(j['low_stock_count']),
        outOfStockCount: _int(j['out_of_stock_count']),
        expiringSoonCount: _int(j['expiring_soon_count']),
        transfersAwaitingApproval: _int(j['transfers_awaiting_approval']),
        transfersAwaitingReceipt: _int(j['transfers_awaiting_receipt']),
        pendingRefunds: j['pending_refunds'] == null ? null : _int(j['pending_refunds']),
        unreadNotifications: _int(j['unread_notifications']),
        generatedAt: _time(j['generated_at']),
      );
}

class StaffOrderItem {
  final String productName;
  final String variantLabel;
  final int quantity;
  final double unitPrice;

  const StaffOrderItem({
    required this.productName,
    required this.variantLabel,
    required this.quantity,
    required this.unitPrice,
  });

  double get total => unitPrice * quantity;

  factory StaffOrderItem.fromJson(Map<String, dynamic> j) => StaffOrderItem(
        productName: (j['product_name'] as String?) ?? '',
        variantLabel: (j['variant_label'] as String?) ?? '',
        quantity: _int(j['quantity']),
        unitPrice: _num(j['unit_price']),
      );
}

class StaffPaymentInfo {
  final String id;
  final String method; // gcash | maya | card | cash
  final String status; // pending | processing | success | failed | refunded
  final String referenceNumber;
  final double amount;

  const StaffPaymentInfo({
    required this.id,
    required this.method,
    required this.status,
    required this.referenceNumber,
    required this.amount,
  });

  bool get isPaid => status == 'success';

  factory StaffPaymentInfo.fromJson(Map<String, dynamic> j) => StaffPaymentInfo(
        id: (j['id'] as String?) ?? '',
        method: (j['method'] as String?) ?? '',
        status: (j['status'] as String?) ?? 'pending',
        referenceNumber: (j['reference_number'] as String?) ?? '',
        amount: _num(j['amount']),
      );
}

/// An order (online or walk-in) as staff see it.
class StaffOrder {
  final String id;
  final DateTime createdAt;
  final OrderStatus status;
  final String branchName;
  final bool isDelivery;
  final double subtotal;
  final double discount;
  final double deliveryFee;
  final double total;
  final String paymentMethod;
  final int pointsEarned;
  final String customerNotes;
  final String contactPhone;
  final String? deliveryAddressText;
  final String? customerName; // null for walk-in sales
  final bool isPos;
  final String? cashierName;
  final double? cashReceived;
  final double? changeGiven;
  final List<StaffOrderItem> items;
  final StaffPaymentInfo? payment;

  const StaffOrder({
    required this.id,
    required this.createdAt,
    required this.status,
    required this.branchName,
    required this.isDelivery,
    required this.subtotal,
    required this.discount,
    required this.deliveryFee,
    required this.total,
    required this.paymentMethod,
    required this.pointsEarned,
    required this.customerNotes,
    required this.contactPhone,
    required this.deliveryAddressText,
    required this.customerName,
    required this.isPos,
    required this.cashierName,
    required this.cashReceived,
    required this.changeGiven,
    required this.items,
    required this.payment,
  });

  String get customerLabel => customerName ?? (isPos ? 'Walk-in customer' : 'Customer');
  String get channelLabel => isPos ? 'In-store' : (isDelivery ? 'Delivery' : 'Pickup');
  bool get isPaid => payment?.isPaid ?? false;

  factory StaffOrder.fromJson(Map<String, dynamic> j) => StaffOrder(
        id: j['id'] as String,
        createdAt: _time(j['created_at']),
        status: OrderStatus.values.byName(j['status'] as String),
        branchName: (j['branch_name'] as String?) ?? '',
        isDelivery: (j['is_delivery'] as bool?) ?? false,
        subtotal: _num(j['subtotal']),
        discount: _num(j['discount']),
        deliveryFee: _num(j['delivery_fee']),
        total: _num(j['total']),
        paymentMethod: (j['payment_method'] as String?) ?? '',
        pointsEarned: _int(j['points_earned']),
        customerNotes: (j['customer_notes'] as String?) ?? '',
        contactPhone: (j['contact_phone'] as String?) ?? '',
        deliveryAddressText: j['delivery_address_text'] as String?,
        customerName: j['customer_name'] as String?,
        isPos: (j['is_pos'] as bool?) ?? false,
        cashierName: j['cashier_name'] as String?,
        cashReceived: j['cash_received'] == null ? null : _num(j['cash_received']),
        changeGiven: j['change_given'] == null ? null : _num(j['change_given']),
        items: ((j['items'] as List?) ?? const [])
            .map((e) => StaffOrderItem.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        payment: j['payment'] == null
            ? null
            : StaffPaymentInfo.fromJson(Map<String, dynamic>.from(j['payment'] as Map)),
      );
}

class TransferBatchLine {
  final String batchCode;
  final DateTime expirationDate;
  final int quantity;

  const TransferBatchLine({
    required this.batchCode,
    required this.expirationDate,
    required this.quantity,
  });

  factory TransferBatchLine.fromJson(Map<String, dynamic> j) => TransferBatchLine(
        batchCode: (j['batch_code'] as String?) ?? '',
        expirationDate: DateTime.parse(j['expiration_date'] as String),
        quantity: _int(j['quantity']),
      );
}

class StockTransfer {
  final String id;
  final String status; // requested | in_transit | received | rejected | cancelled
  final int quantity;
  final String note;
  final DateTime requestedAt;
  final String fromBranchId;
  final String fromBranchName;
  final String toBranchId;
  final String toBranchName;
  final String productName;
  final String variantLabel;
  final List<TransferBatchLine> batches;

  const StockTransfer({
    required this.id,
    required this.status,
    required this.quantity,
    required this.note,
    required this.requestedAt,
    required this.fromBranchId,
    required this.fromBranchName,
    required this.toBranchId,
    required this.toBranchName,
    required this.productName,
    required this.variantLabel,
    required this.batches,
  });

  String get statusLabel {
    switch (status) {
      case 'requested':
        return 'Requested';
      case 'in_transit':
        return 'In transit';
      case 'received':
        return 'Received';
      case 'rejected':
        return 'Declined';
      case 'cancelled':
        return 'Cancelled';
      default:
        return status;
    }
  }

  factory StockTransfer.fromJson(Map<String, dynamic> j) => StockTransfer(
        id: j['id'] as String,
        status: j['status'] as String,
        quantity: _int(j['quantity']),
        note: (j['note'] as String?) ?? '',
        requestedAt: _time(j['requested_at']),
        fromBranchId: j['from_branch_id'] as String,
        fromBranchName: (j['from_branch_name'] as String?) ?? '',
        toBranchId: j['to_branch_id'] as String,
        toBranchName: (j['to_branch_name'] as String?) ?? '',
        productName: (j['product_name'] as String?) ?? '',
        variantLabel: (j['variant_label'] as String?) ?? '',
        batches: ((j['batches'] as List?) ?? const [])
            .map((e) => TransferBatchLine.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
}

class StaffNotification {
  final String id;
  final String category; // order | inventory | transfer | refund | system
  final String title;
  final String body;
  final String? reference;
  final bool read;
  final DateTime createdAt;

  const StaffNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.body,
    required this.reference,
    required this.read,
    required this.createdAt,
  });

  StaffNotification asRead() => StaffNotification(
        id: id,
        category: category,
        title: title,
        body: body,
        reference: reference,
        read: true,
        createdAt: createdAt,
      );

  factory StaffNotification.fromRow(Map<String, dynamic> r) => StaffNotification(
        id: r['id'] as String,
        category: (r['category'] as String?) ?? 'system',
        title: (r['title'] as String?) ?? '',
        body: (r['body'] as String?) ?? '',
        reference: r['reference'] as String?,
        read: (r['read'] as bool?) ?? false,
        createdAt: _time(r['created_at']),
      );
}

class StaffRefund {
  final String id;
  final String orderId;
  final String status; // pending | approved | processing | completed | rejected
  final String reason;
  final String notes;
  final double amount;
  final String paymentMethod;
  final DateTime createdAt;
  final String? customerName;
  final List<StaffOrderItem> items;

  const StaffRefund({
    required this.id,
    required this.orderId,
    required this.status,
    required this.reason,
    required this.notes,
    required this.amount,
    required this.paymentMethod,
    required this.createdAt,
    required this.customerName,
    required this.items,
  });

  bool get isOpen => status == 'pending' || status == 'approved' || status == 'processing';

  factory StaffRefund.fromJson(Map<String, dynamic> j) => StaffRefund(
        id: j['id'] as String,
        orderId: j['order_id'] as String,
        status: j['status'] as String,
        reason: (j['reason'] as String?) ?? '',
        notes: (j['notes'] as String?) ?? '',
        amount: _num(j['amount']),
        paymentMethod: (j['payment_method'] as String?) ?? '',
        createdAt: _time(j['created_at']),
        customerName: j['customer_name'] as String?,
        items: ((j['items'] as List?) ?? const [])
            .map((e) => StaffOrderItem.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
}

/// A loyalty customer found at the register.
class CustomerMatch {
  final String uid;
  final String name;
  final int points;

  const CustomerMatch({required this.uid, required this.name, required this.points});

  factory CustomerMatch.fromJson(Map<String, dynamic> j) => CustomerMatch(
        uid: j['firebase_uid'] as String,
        name: ((j['full_name'] as String?) ?? '').trim().isEmpty
            ? 'Loyalty customer'
            : j['full_name'] as String,
        points: _int(j['points_balance']),
      );
}

/// What the database returns after ringing up a sale.
class PosSaleResult {
  final String orderId;
  final double total;
  final int pointsEarned;
  final double change;
  final String paymentId;
  final bool alreadyRecorded;

  const PosSaleResult({
    required this.orderId,
    required this.total,
    required this.pointsEarned,
    required this.change,
    required this.paymentId,
    required this.alreadyRecorded,
  });

  factory PosSaleResult.fromJson(Map<String, dynamic> j) => PosSaleResult(
        orderId: j['order_id'] as String,
        total: _num(j['total']),
        pointsEarned: _int(j['points_earned']),
        change: _num(j['change']),
        paymentId: (j['payment_id'] as String?) ?? '',
        alreadyRecorded: (j['already_recorded'] as bool?) ?? false,
      );
}
