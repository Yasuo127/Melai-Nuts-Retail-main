/// Customer business-profile data — everything about the customer beyond
/// their login identity (which stays in Firebase). Backed by the
/// `customer_profiles` table in Supabase.
class CustomerProfile {
  final String firebaseUid;
  final String fullName;
  final String email;
  final String phone;
  final String? rfidCardNumber;
  final String? defaultBranchId;

  const CustomerProfile({
    required this.firebaseUid,
    required this.fullName,
    required this.email,
    required this.phone,
    this.rfidCardNumber,
    this.defaultBranchId,
  });

  factory CustomerProfile.fromRow(Map<String, dynamic> row) {
    return CustomerProfile(
      firebaseUid: row['firebase_uid'] as String,
      fullName: (row['full_name'] as String?) ?? '',
      email: (row['email'] as String?) ?? '',
      phone: (row['phone'] as String?) ?? '',
      rfidCardNumber: row['rfid_card_number'] as String?,
      defaultBranchId: row['default_branch_id'] as String?,
    );
  }

  CustomerProfile copyWith({String? fullName, String? email, String? phone}) {
    return CustomerProfile(
      firebaseUid: firebaseUid,
      fullName: fullName ?? this.fullName,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      rfidCardNumber: rfidCardNumber,
      defaultBranchId: defaultBranchId,
    );
  }
}

/// A saved delivery address, backed by `customer_addresses`.
class CustomerAddress {
  final String id;
  final String label;
  final String recipientName;
  final String phone;
  final String line1;
  final String city;
  final String province;
  final String postalCode;
  final bool isDefault;

  const CustomerAddress({
    required this.id,
    required this.label,
    required this.recipientName,
    required this.phone,
    required this.line1,
    required this.city,
    required this.province,
    required this.postalCode,
    this.isDefault = false,
  });

  factory CustomerAddress.fromRow(Map<String, dynamic> row) {
    return CustomerAddress(
      id: row['id'] as String,
      label: (row['label'] as String?) ?? 'Home',
      recipientName: row['recipient_name'] as String,
      phone: row['phone'] as String,
      line1: row['line1'] as String,
      city: row['city'] as String,
      province: (row['province'] as String?) ?? 'Laguna',
      postalCode: (row['postal_code'] as String?) ?? '',
      isDefault: (row['is_default'] as bool?) ?? false,
    );
  }

  String get fullAddress => '$line1, $city, $province $postalCode'.trim();
}
