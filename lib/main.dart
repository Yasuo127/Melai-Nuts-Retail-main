import 'dart:async';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'app/app.dart';
import 'core/services/audit_service.dart';
import 'core/services/branch_controller.dart';
import 'core/services/connectivity_service.dart';
import 'core/services/customer_data_store.dart';
import 'core/services/data_sync_service.dart';
import 'core/services/staff_session_store.dart';
import 'core/services/staff_store.dart';
import 'core/services/supabase_service.dart';
import 'core/services/sync_service.dart';
import 'data/repositories/products_repository.dart';
import 'features/customer/cart_controller.dart';
import 'firebase_options.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  } on FirebaseException catch (e) {
    if (e.code != 'duplicate-app') rethrow;
  }
  await DataSyncService.instance.initializeLocalDatabase();
  await SupabaseService.instance.initialize();
  StaffSessionStore.instance.registerResetHook(StaffStore.instance.clear);
  // In-memory sync counters belong to one staff member; the queue itself stays
  // on disk, owned by (and only ever sent for) its own Firebase UID.
  StaffSessionStore.instance.registerResetHook(SyncService.instance.resetState);
  StaffSessionStore.instance.bindToAuth();
  AuditService.instance.registerWithSync();
  SyncService.instance.attach(
    session: StaffSessionStore.instance,
    connectivity: ConnectivityService.instance,
  );
  unawaited(ProductsRepository.instance.loadCatalog());
  ConnectivityService.instance.onReconnect(() async {
    final uid = FirebaseAuth.instance.currentUser?.uid;
    if (uid == null) {
      // Guest browsing: still worth refreshing the public catalog.
      await ProductsRepository.instance.loadCatalog(
        branchId: BranchController.instance.selectedBranch?.id,
      );
      return;
    }
    if (StaffSessionStore.instance.ownerUid == uid) {
      await Future.wait([
        ProductsRepository.instance.loadCatalog(
          branchId: BranchController.instance.selectedBranch?.id,
        ),
        StaffSessionStore.instance.refresh(),
      ]);
      if (StaffStore.instance.profile != null) {
        await StaffStore.instance.refreshAll();
      }
      return;
    }
    await Future.wait([
      ProductsRepository.instance.loadCatalog(
        branchId: BranchController.instance.selectedBranch?.id,
      ),
      CustomerDataStore.instance.refresh(),
      CartController.instance.refresh(),
    ]);
  });
  unawaited(ConnectivityService.instance.start());

  runApp(const MelaiNutsApp());
}