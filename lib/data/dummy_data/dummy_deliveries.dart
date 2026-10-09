import '../../core/services/staff_store.dart';
import '../models/delivery.dart';

// NOTE: despite the folder name this file holds NO sample data any more. It
// is a thin read-only view over the real deliveries [StaffStore] loads from
// the database (see `DeliveryRepository`/`staff_get_deliveries`), kept so the
// Delivery screens that already import these names keep working. Everything
// is scoped to the branch the signed-in staff member (or owner) is currently
// viewing.

/// Real deliveries for the active branch.
List<Delivery> get kDeliveries => StaffStore.instance.deliveries;

List<Delivery> get activeDeliveries =>
    kDeliveries.where((d) => d.status.isActive).toList();

List<Delivery> get pastDeliveries =>
    kDeliveries.where((d) => !d.status.isActive).toList();
