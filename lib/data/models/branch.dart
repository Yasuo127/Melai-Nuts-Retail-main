/// A physical Melai Nuts branch, backed by the `branches` table.
///
/// This is real, staff/owner-managed data — there is no fabricated branch
/// anywhere in the customer app. When a customer picks a branch, the
/// product catalog's stock/availability is re-scoped to this branch's
/// `branch_inventory` rows (see `ProductsRepository.loadCatalog`).
class Branch {
  final String id;
  final String name;
  final String address;
  final String? contactPhone;
  final String? operatingHours;

  /// Operating status shown to customers — `false` means "temporarily
  /// closed", not deleted.
  final bool isActive;
  final bool supportsDelivery;
  final bool supportsPickup;
  final double deliveryFee;

  const Branch({
    required this.id,
    required this.name,
    required this.address,
    required this.isActive,
    required this.supportsDelivery,
    required this.supportsPickup,
    required this.deliveryFee,
    this.contactPhone,
    this.operatingHours,
  });

  factory Branch.fromRow(Map<String, dynamic> row) {
    return Branch(
      id: row['id'] as String,
      name: row['name'] as String,
      address: (row['address'] as String?) ?? '',
      contactPhone: row['contact_phone'] as String?,
      operatingHours: row['operating_hours'] as String?,
      isActive: (row['is_active'] as bool?) ?? true,
      supportsDelivery: (row['supports_delivery'] as bool?) ?? true,
      supportsPickup: (row['supports_pickup'] as bool?) ?? true,
      deliveryFee: (row['delivery_fee'] as num?)?.toDouble() ?? 0,
    );
  }
}
