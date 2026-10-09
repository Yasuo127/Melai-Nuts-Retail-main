import 'package:flutter/material.dart';
import '../../app/routes.dart';
import '../../data/models/user_role.dart';
import '../services/auth_service.dart';
import '../theme/app_colors.dart';
import '../theme/app_text_styles.dart';

/// Temporary landing screen shown right after sign-in for a role whose full
/// portal hasn't been implemented yet (built out in later batches).
///
/// Confirms that role-based navigation works end-to-end and gives a
/// consistent, on-brand "coming soon" placeholder plus a working logout
/// action back to the Login screen.
class PortalPlaceholderScreen extends StatelessWidget {
  final UserRole role;
  final List<String> upcomingFeatures;

  const PortalPlaceholderScreen({
    super.key,
    required this.role,
    required this.upcomingFeatures,
  });

  Future<void> _logout(BuildContext context) async {
    final navigator = Navigator.of(context);
    await AuthService.instance.signOut();
    navigator.pushNamedAndRemoveUntil(AppRoutes.login, (route) => false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('${role.label} Portal'),
        actions: [
          IconButton(
            tooltip: 'Login & Security',
            onPressed: () =>
                Navigator.of(context).pushNamed(AppRoutes.security),
            icon: const Icon(Icons.shield_outlined),
          ),
          IconButton(
            tooltip: 'Logout',
            onPressed: () => _logout(context),
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                children: [
                  Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: role.color.withValues(alpha: 0.12),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(role.icon, color: role.color, size: 32),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Signed in as ${role.label}',
                    style: AppTextStyles.headlineSm,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Authentication flow complete. This portal\'s screens will be built out in the next development batch.',
                    textAlign: TextAlign.center,
                    style: AppTextStyles.bodyMd,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.surfaceContainerLow,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Coming up in this portal', style: AppTextStyles.titleMd),
                  const SizedBox(height: 10),
                  ...upcomingFeatures.map(
                    (f) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        children: [
                          Icon(
                            Icons.circle,
                            size: 6,
                            color: AppColors.textSecondary,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(f, style: AppTextStyles.bodyMd),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _logout(context),
                icon: const Icon(Icons.logout_rounded, size: 18),
                label: const Text('Logout'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
