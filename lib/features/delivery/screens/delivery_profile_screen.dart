import 'package:flutter/material.dart';
import '../../../core/services/staff_store.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/secondary_button.dart';
import '../../settings/screens/logout_confirmation_screen.dart';
import '../../settings/screens/settings_screen.dart';

/// Rider profile content — the "Profile" tab of the Delivery Portal. Mirrors
/// the Staff/Owner profile pattern (avatar header, info card, settings list)
/// and holds the Log Out button. Has no Scaffold of its own so it can be used
/// as a tab body.
class DeliveryProfileBody extends StatelessWidget {
  const DeliveryProfileBody({super.key});

  @override
  Widget build(BuildContext context) {
    final profile = StaffStore.instance.profile;
    final fullName = profile?.fullName ?? '';
    final initials = fullName.trim().isEmpty
        ? '?'
        : fullName.trim().split(RegExp(r'\s+')).take(2).map((w) => w[0].toUpperCase()).join();
    final branchName = StaffStore.instance.activeBranchName;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.sm,
          ),
          child: Column(
            children: [
              CircleAvatar(
                radius: 40,
                backgroundColor: AppColors.roleDelivery,
                child: Text(
                  initials,
                  style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(height: 12),
              Text(fullName.isEmpty ? 'Delivery Rider' : fullName, style: AppTextStyles.headlineSm),
              Text(branchName.isEmpty ? 'Delivery Rider' : 'Delivery Rider • $branchName', style: AppTextStyles.bodyMd),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.sm,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ProfileItem(icon: Icons.mail_outline_rounded, title: 'Email', value: profile?.email ?? ''),
              const Divider(height: 24),
              _ProfileItem(icon: Icons.storefront_outlined, title: 'Branch', value: branchName.isEmpty ? '—' : branchName),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
            border: Border.all(color: AppColors.border),
            boxShadow: AppShadows.sm,
          ),
          child: Material(type: MaterialType.transparency, child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.settings_outlined, color: AppColors.darkBrown),
                title: Text('App Settings', style: AppTextStyles.labelLg),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const SettingsScreen()),
                ),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.help_outline_rounded, color: AppColors.darkBrown),
                title: Text('Help & Support', style: AppTextStyles.labelLg),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () {},
              ),
            ],
          )),
        ),
        const SizedBox(height: AppSpacing.lg),
        SecondaryButton(
          label: 'Log Out',
          icon: Icons.logout_rounded,
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const LogoutConfirmationScreen()),
          ),
        ),
      ],
    );
  }
}

class _ProfileItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String value;
  const _ProfileItem({required this.icon, required this.title, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 20, color: AppColors.textSecondary),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: AppTextStyles.bodySm),
              Text(value, style: AppTextStyles.labelLg),
            ],
          ),
        ),
      ],
    );
  }
}