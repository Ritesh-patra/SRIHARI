import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import '../core/api_client.dart';
import '../core/api_config.dart';
import '../core/offline_queue.dart';
import '../core/sync_service.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_widgets.dart';
import 'dtr_survey_form.dart';
import 'my_progress_screen.dart';
import 'notifications_screen.dart';

typedef ProfileJump = void Function(int tabIndex);
typedef ProfileUserChanged = Future<void> Function(Map<String, dynamic>? user);
typedef ProfileLogout = Future<void> Function();

/// Shared FE/Manager account actions for Home menu + Profile tab.
enum ProfileAction {
  editProfile,
  changePassword,
  avatar,
  drafts,
  mySurveys,
  myProgress,
  notifications,
  assignedWork,
  syncStatus,
  helpSupport,
  about,
  logout,
}

class ProfileFeature {
  const ProfileFeature({
    required this.action,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.destructive = false,
  });

  final ProfileAction action;
  final String title;
  final String subtitle;
  final IconData icon;
  final bool destructive;
}

List<ProfileFeature> profileFeaturesFor({required bool isManager}) {
  return [
    const ProfileFeature(
      action: ProfileAction.editProfile,
      title: 'Edit profile',
      subtitle: 'Update name and phone',
      icon: Icons.badge_outlined,
    ),
    const ProfileFeature(
      action: ProfileAction.avatar,
      title: 'Profile picture',
      subtitle: 'Upload or change photo',
      icon: Icons.photo_camera_outlined,
    ),
    const ProfileFeature(
      action: ProfileAction.changePassword,
      title: 'Change password',
      subtitle: 'Verify current, then set new',
      icon: Icons.lock_outline_rounded,
    ),
    if (!isManager)
      const ProfileFeature(
        action: ProfileAction.drafts,
        title: 'Saved drafts',
        subtitle: 'Server drafts & offline queue',
        icon: Icons.drafts_outlined,
      ),
    ProfileFeature(
      action: ProfileAction.mySurveys,
      title: isManager ? 'Approvals' : 'My surveys',
      subtitle: isManager ? 'Pending review inbox' : 'Draft · Pending · Rejected · Approved',
      icon: isManager ? Icons.fact_check_outlined : Icons.bolt_outlined,
    ),
    const ProfileFeature(
      action: ProfileAction.myProgress,
      title: 'My progress',
      subtitle: 'Dashboard stats & pipeline',
      icon: Icons.insights_outlined,
    ),
    const ProfileFeature(
      action: ProfileAction.notifications,
      title: 'Notifications',
      subtitle: 'Alerts and work updates',
      icon: Icons.notifications_none_rounded,
    ),
    ProfileFeature(
      action: ProfileAction.assignedWork,
      title: isManager ? 'Team assignments' : 'Assigned work',
      subtitle: isManager ? 'Jobs you assigned' : 'Work sent by your manager',
      icon: Icons.assignment_outlined,
    ),
    const ProfileFeature(
      action: ProfileAction.syncStatus,
      title: 'Offline / sync',
      subtitle: 'Pending uploads & connectivity',
      icon: Icons.cloud_sync_outlined,
    ),
    const ProfileFeature(
      action: ProfileAction.helpSupport,
      title: 'Help & support',
      subtitle: 'Contact and field tips',
      icon: Icons.support_agent_rounded,
    ),
    const ProfileFeature(
      action: ProfileAction.about,
      title: 'About SEAS',
      subtitle: 'App version and brand info',
      icon: Icons.info_outline_rounded,
    ),
    const ProfileFeature(
      action: ProfileAction.logout,
      title: 'Logout',
      subtitle: 'Sign out of this device',
      icon: Icons.logout_rounded,
      destructive: true,
    ),
  ];
}

Future<void> showProfileMenuSheet({
  required BuildContext context,
  required Map<String, dynamic>? user,
  required bool isManager,
  required ProfileJump onJump,
  required ProfileLogout onLogout,
  required ProfileUserChanged onUserChanged,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) {
      return DraggableScrollableSheet(
        initialChildSize: 0.72,
        minChildSize: 0.45,
        maxChildSize: 0.94,
        expand: false,
        builder: (_, scroll) {
          return Container(
            decoration: const BoxDecoration(
              color: SeasColors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: Column(
              children: [
                const SizedBox(height: 10),
                Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: SeasColors.ink200,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                  child: Row(
                    children: [
                      Text(
                        'Menu',
                        style: GoogleFonts.plusJakartaSans(
                          fontWeight: FontWeight.w800,
                          fontSize: 20,
                          color: SeasColors.ink950,
                        ),
                      ),
                      const Spacer(),
                      IconButton(
                        onPressed: () => Navigator.pop(ctx),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ProfileFeatureList(
                    user: user,
                    isManager: isManager,
                    scrollController: scroll,
                    dense: true,
                    // Close sheet first, then logout after the frame so
                    // pushAndRemoveUntil does not race the modal route.
                    onLogout: () async {
                      Navigator.of(ctx).pop();
                      await Future<void>.delayed(const Duration(milliseconds: 50));
                      await onLogout();
                    },
                    onJump: (i) {
                      Navigator.pop(ctx);
                      onJump(i);
                    },
                    onUserChanged: onUserChanged,
                  ),
                ),
              ],
            ),
          );
        },
      );
    },
  );
}

class ProfileFeatureList extends StatelessWidget {
  const ProfileFeatureList({
    super.key,
    required this.user,
    required this.isManager,
    required this.onJump,
    required this.onLogout,
    required this.onUserChanged,
    this.scrollController,
    this.dense = false,
    this.showHeader = false,
  });

  final Map<String, dynamic>? user;
  final bool isManager;
  final ProfileJump onJump;
  final ProfileLogout onLogout;
  final ProfileUserChanged onUserChanged;
  final ScrollController? scrollController;
  final bool dense;
  final bool showHeader;

  Future<void> _handle(BuildContext context, ProfileAction action) async {
    switch (action) {
      case ProfileAction.editProfile:
        final updated = await Navigator.of(context).push<Map<String, dynamic>>(
          MaterialPageRoute(builder: (_) => EditProfileScreen(user: user)),
        );
        if (updated != null) await onUserChanged(updated);
      case ProfileAction.changePassword:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
        );
      case ProfileAction.avatar:
        await _pickAndUploadAvatar(context);
      case ProfileAction.drafts:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const SavedDraftsScreen()),
        );
      case ProfileAction.mySurveys:
        onJump(1);
      case ProfileAction.myProgress:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const MyProgressFilterScreen()),
        );
      case ProfileAction.notifications:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
        );
      case ProfileAction.assignedWork:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => AssignedWorkScreen(isManager: isManager)),
        );
      case ProfileAction.syncStatus:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const SyncStatusScreen()),
        );
      case ProfileAction.helpSupport:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const HelpSupportScreen()),
        );
      case ProfileAction.about:
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const AboutSeasScreen()),
        );
      case ProfileAction.logout:
        final ok = await showDialog<bool>(
          context: context,
          useRootNavigator: true,
          builder: (ctx) => AlertDialog(
            title: Text('Logout?', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
            content: const Text('You will need to sign in again on this device.'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Logout'),
              ),
            ],
          ),
        );
        if (ok == true) await onLogout();
    }
  }

  Future<void> _pickAndUploadAvatar(BuildContext context) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: SeasColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded, color: SeasColors.volt),
              title: const Text('Take photo'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined, color: SeasColors.ink900),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (source == null || !context.mounted) return;

    final picker = ImagePicker();
    final file = await picker.pickImage(source: source, imageQuality: 85, maxWidth: 1200);
    if (file == null || !context.mounted) return;

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator(color: SeasColors.volt)),
    );

    try {
      final res = await api.updateAvatar(file.path);
      final u = res['user'];
      if (u is Map) await onUserChanged(Map<String, dynamic>.from(u));
      if (context.mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message']?.toString() ?? 'Profile picture updated')),
        );
      }
    } catch (e) {
      if (context.mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final features = profileFeaturesFor(isManager: isManager);
    final name = user?['name']?.toString() ?? 'User';
    final email = user?['email']?.toString() ?? '';
    final phone = user?['phone']?.toString() ?? '';
    final role = user?['role_label']?.toString() ?? '';
    final avatarUrl = ApiConfig.mediaUrl(user?['avatar_url']?.toString() ?? user?['avatar']?.toString());
    final initial = name.isNotEmpty ? name[0].toUpperCase() : 'U';

    return ListView(
      controller: scrollController,
      padding: EdgeInsets.fromLTRB(16, dense ? 4 : 16, 16, 28),
      children: [
        if (showHeader) ...[
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(24),
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [SeasColors.ink950, SeasColors.ink800],
              ),
              boxShadow: SeasShadows.seasLg,
            ),
            child: Row(
              children: [
                _AvatarCircle(size: 64, initial: initial, imageUrl: avatarUrl, borderColor: SeasColors.volt),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name, style: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)),
                      const SizedBox(height: 2),
                      Text(email, style: TextStyle(color: Colors.white.withValues(alpha: 0.55), fontSize: 13)),
                      if (phone.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(phone, style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 12)),
                      ],
                      const SizedBox(height: 8),
                      SeasBadge(role.isEmpty ? '—' : role, tone: SeasBadgeTone.volt),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'ACCOUNT & TOOLS',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.5,
              color: SeasColors.volt,
            ),
          ),
          const SizedBox(height: 10),
        ],
        SeasCard(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Column(
            children: [
              for (var i = 0; i < features.length; i++) ...[
                if (i > 0) const Divider(height: 1, indent: 68, endIndent: 16),
                _FeatureTile(
                  feature: features[i],
                  onTap: () => _handle(context, features[i].action),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _AvatarCircle extends StatelessWidget {
  const _AvatarCircle({
    required this.size,
    required this.initial,
    this.imageUrl,
    this.borderColor = SeasColors.white,
  });

  final double size;
  final String initial;
  final String? imageUrl;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: size,
      width: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: SeasColors.volt,
        border: Border.all(color: borderColor, width: 2.5),
        boxShadow: SeasShadows.glow,
        image: imageUrl == null
            ? null
            : DecorationImage(image: NetworkImage(imageUrl!), fit: BoxFit.cover),
      ),
      alignment: Alignment.center,
      child: imageUrl == null
          ? Text(
              initial,
              style: GoogleFonts.plusJakartaSans(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontSize: size * 0.38,
              ),
            )
          : null,
    );
  }
}

class _FeatureTile extends StatelessWidget {
  const _FeatureTile({required this.feature, required this.onTap});
  final ProfileFeature feature;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = feature.destructive ? SeasColors.volt : SeasColors.ink950;
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
      leading: SeasIconTile(
        icon: feature.icon,
        bg: feature.destructive ? SeasColors.voltSoft : SeasColors.canvasSoft,
        fg: fg,
      ),
      title: Text(
        feature.title,
        style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14.5, color: fg),
      ),
      subtitle: Text(
        feature.subtitle,
        style: const TextStyle(color: SeasColors.ink400, fontSize: 12, height: 1.25),
      ),
      trailing: Icon(Icons.chevron_right_rounded, color: feature.destructive ? SeasColors.volt : SeasColors.ink400),
    );
  }
}

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key, required this.user});
  final Map<String, dynamic>? user;

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  late final TextEditingController _name;
  late final TextEditingController _phone;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.user?['name']?.toString() ?? '');
    _phone = TextEditingController(text: widget.user?['phone']?.toString() ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      setState(() => error = 'Name is required');
      return;
    }
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final res = await api.updateProfile(name: name, phone: _phone.text.trim());
      final user = Map<String, dynamic>.from(res['user'] as Map);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message']?.toString() ?? 'Profile updated')),
      );
      Navigator.pop(context, user);
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Account',
      title: 'Edit profile',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          SeasCard(
            child: Column(
              children: [
                TextField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(
                    labelText: 'Full name',
                    prefixIcon: Icon(Icons.person_outline_rounded),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+\-\s]'))],
                  decoration: const InputDecoration(
                    labelText: 'Phone',
                    hintText: 'Optional',
                    prefixIcon: Icon(Icons.phone_outlined),
                  ),
                ),
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Email: ${widget.user?['email'] ?? '—'} (managed by admin)',
                    style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                  ),
                ),
              ],
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
          ],
          const SizedBox(height: 20),
          SeasPrimaryButton(label: saving ? 'SAVING…' : 'SAVE CHANGES', onPressed: saving ? null : _save),
        ],
      ),
    );
  }
}

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  bool saving = false;
  bool showCurrent = false;
  bool showNext = false;
  String? error;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_current.text.isEmpty || _next.text.isEmpty || _confirm.text.isEmpty) {
      setState(() => error = 'Fill all password fields');
      return;
    }
    if (_next.text != _confirm.text) {
      setState(() => error = 'New password and confirmation do not match');
      return;
    }
    if (_next.text.length < 8) {
      setState(() => error = 'New password must be at least 8 characters');
      return;
    }
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final res = await api.changePassword(
        currentPassword: _current.text,
        password: _next.text,
        passwordConfirmation: _confirm.text,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message']?.toString() ?? 'Password changed')),
      );
      Navigator.pop(context);
    } catch (e) {
      setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Security',
      title: 'Change password',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          SeasCard(
            child: Column(
              children: [
                TextField(
                  controller: _current,
                  obscureText: !showCurrent,
                  decoration: InputDecoration(
                    labelText: 'Current password',
                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                    suffixIcon: IconButton(
                      onPressed: () => setState(() => showCurrent = !showCurrent),
                      icon: Icon(showCurrent ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _next,
                  obscureText: !showNext,
                  decoration: InputDecoration(
                    labelText: 'New password',
                    prefixIcon: const Icon(Icons.key_rounded),
                    suffixIcon: IconButton(
                      onPressed: () => setState(() => showNext = !showNext),
                      icon: Icon(showNext ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _confirm,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirm new password',
                    prefixIcon: Icon(Icons.verified_user_outlined),
                  ),
                ),
              ],
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600)),
          ],
          const SizedBox(height: 20),
          SeasPrimaryButton(label: saving ? 'UPDATING…' : 'UPDATE PASSWORD', onPressed: saving ? null : _save),
        ],
      ),
    );
  }
}

class SavedDraftsScreen extends StatefulWidget {
  const SavedDraftsScreen({super.key});

  @override
  State<SavedDraftsScreen> createState() => _SavedDraftsScreenState();
}

class _SavedDraftsScreenState extends State<SavedDraftsScreen> {
  List serverDrafts = [];
  List<OfflineDraft> offline = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      offline = await offlineQueue.all();
      final res = await api.get('/surveys?status=draft&per_page=50');
      serverDrafts = (res['data'] as List?) ?? [];
    } catch (e) {
      offline = await offlineQueue.all();
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final empty = serverDrafts.isEmpty && offline.isEmpty;
    return SeasPageScaffold(
      eyebrow: 'Work in progress',
      title: 'Saved drafts',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded))],
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : empty
              ? SeasEmptyState(
                  title: 'No drafts',
                  subtitle: error ?? 'Save a DTR survey as draft to find it here.',
                  icon: Icons.drafts_outlined,
                )
              : RefreshIndicator(
                  color: SeasColors.volt,
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                    children: [
                      if (error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontSize: 12)),
                        ),
                      if (offline.isNotEmpty) ...[
                        Text('Offline queue (${offline.length})', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 8),
                        ...offline.map((d) => Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: SeasCard(
                                onTap: () async {
                                  await Navigator.of(context).push(MaterialPageRoute(
                                    builder: (_) => DtrSurveyFormScreen(localId: d.localId, serverId: d.serverId),
                                  ));
                                  _load();
                                },
                                padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                                child: Row(
                                  children: [
                                    SeasIconTile(icon: Icons.cloud_off_rounded, bg: SeasColors.warningSoft, fg: SeasColors.warning),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text('Offline ${d.action}', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                                          Text('Tap to continue editing', style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                                        ],
                                      ),
                                    ),
                                    SeasBadge(d.action, tone: SeasBadgeTone.warning),
                                  ],
                                ),
                              ),
                            )),
                        const SizedBox(height: 12),
                      ],
                      if (serverDrafts.isNotEmpty)
                        Text('Server drafts', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      ...serverDrafts.map((raw) {
                        final s = raw as Map;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: SeasCard(
                            onTap: () async {
                              await Navigator.of(context).push(MaterialPageRoute(
                                builder: (_) => DtrSurveyFormScreen(serverId: s['id'] as int?),
                              ));
                              _load();
                            },
                            padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                            child: Row(
                              children: [
                                SeasIconTile(icon: Icons.drafts_outlined, bg: SeasColors.voltSoft, fg: SeasColors.volt),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('${s['dtr_name'] ?? 'DTR'}', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                                      Text('${s['feeder_name'] ?? 'Feeder'}', style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                                    ],
                                  ),
                                ),
                                const SeasBadge('draft', tone: SeasBadgeTone.warning),
                                const Icon(Icons.chevron_right_rounded, color: SeasColors.ink400),
                              ],
                            ),
                          ),
                        );
                      }),
                    ],
                  ),
                ),
    );
  }
}

class AssignedWorkScreen extends StatefulWidget {
  const AssignedWorkScreen({super.key, required this.isManager});
  final bool isManager;

  @override
  State<AssignedWorkScreen> createState() => _AssignedWorkScreenState();
}

class _AssignedWorkScreenState extends State<AssignedWorkScreen> {
  List items = [];
  bool loading = true;
  String? error;
  /// FE: `active` (open/started) vs `done` (completed / awaiting approval history).
  String filter = 'active';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      // FE active list excludes completed; Completed/Pending tab uses include_all then filters.
      final path = widget.isManager
          ? '/assignments'
          : (filter == 'active' ? '/assignments?status=active' : '/assignments?include_all=1');
      final res = await api.get(path);
      final all = (res['data'] as List?) ?? [];
      if (widget.isManager || filter == 'active') {
        items = all;
      } else {
        items = all.where((e) {
          if (e is! Map) return false;
          final st = '${e['status'] ?? ''}'.toLowerCase();
          return st == 'done' || st == 'closed';
        }).toList();
      }
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
      items = [];
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: widget.isManager ? 'Team' : 'Field work',
      title: widget.isManager ? 'Team assignments' : 'Assigned work',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded))],
      child: Column(
        children: [
          if (!widget.isManager)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Wrap(
                spacing: 8,
                children: [
                  ChoiceChip(
                    label: const Text('Active'),
                    selected: filter == 'active',
                    onSelected: (_) {
                      if (filter == 'active') return;
                      setState(() => filter = 'active');
                      _load();
                    },
                  ),
                  ChoiceChip(
                    label: const Text('Completed / Pending'),
                    selected: filter == 'done',
                    onSelected: (_) {
                      if (filter == 'done') return;
                      setState(() => filter = 'done');
                      _load();
                    },
                  ),
                ],
              ),
            ),
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : items.isEmpty
                    ? SeasEmptyState(
                        title: filter == 'done' ? 'No completed assignments' : 'No active assignments',
                        subtitle: error ??
                            (widget.isManager
                                ? 'Assign date-wise jobs from Team tab.'
                                : (filter == 'done'
                                    ? 'After SLD upload, finished feeders appear here and leave Active.'
                                    : 'Your manager has not assigned open work yet.')),
                        icon: Icons.assignment_outlined,
                      )
                    : RefreshIndicator(
                        color: SeasColors.volt,
                        onRefresh: _load,
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                          itemCount: items.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (_, i) {
                            final a = items[i] as Map;
                            final feeder = a['feeder'];
                            final dtr = a['dtr'];
                            final feederName =
                                feeder is Map ? '${feeder['name'] ?? feeder['code'] ?? 'Feeder'}' : 'Feeder';
                            final dtrName = dtr is Map ? '${dtr['name'] ?? dtr['code'] ?? 'DTR'}' : 'Any DTR';
                            final status = '${a['status'] ?? 'open'}';
                            final workDate = '${a['work_date'] ?? ''}'.split('T').first;
                            return SeasCard(
                              padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
                              child: Row(
                                children: [
                                  SeasIconTile(
                                    icon: Icons.assignment_turned_in_outlined,
                                    bg: SeasColors.voltSoft,
                                    fg: SeasColors.volt,
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          '$feederName · $dtrName',
                                          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          workDate.isEmpty
                                              ? '${a['notes'] ?? 'Assigned work'}'
                                              : 'Date $workDate · ${a['notes'] ?? 'Assigned'}',
                                          style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                        ),
                                      ],
                                    ),
                                  ),
                                  SeasBadge(status, tone: badgeToneForStatus(status)),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class SyncStatusScreen extends StatefulWidget {
  const SyncStatusScreen({super.key});

  @override
  State<SyncStatusScreen> createState() => _SyncStatusScreenState();
}

class _SyncStatusScreenState extends State<SyncStatusScreen> {
  bool online = true;
  int pending = 0;
  bool syncing = false;
  String? message;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    online = await syncService.isOnline;
    pending = await offlineQueue.pendingCount();
    if (mounted) setState(() {});
  }

  Future<void> _syncNow() async {
    setState(() {
      syncing = true;
      message = null;
    });
    try {
      final n = await syncService.syncPending();
      await _refresh();
      message = n > 0 ? 'Synced $n item(s).' : (pending == 0 ? 'Nothing pending.' : 'Still waiting — check connection.');
    } catch (e) {
      message = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => syncing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Connectivity',
      title: 'Offline / sync',
      actions: [IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh_rounded))],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          SeasCard(
            child: Column(
              children: [
                _ProfileMetaRow(
                  Icons.wifi_rounded,
                  'Connection',
                  online ? 'Online' : 'Offline',
                ),
                const Divider(height: 24),
                _ProfileMetaRow(
                  Icons.cloud_queue_rounded,
                  'Pending sync',
                  '$pending item${pending == 1 ? '' : 's'}',
                ),
              ],
            ),
          ),
          if (message != null) ...[
            const SizedBox(height: 12),
            Text(message!, style: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600)),
          ],
          const SizedBox(height: 20),
          SeasPrimaryButton(
            label: syncing ? 'SYNCING…' : 'SYNC NOW',
            onPressed: syncing ? null : _syncNow,
          ),
          const SizedBox(height: 12),
          const Text(
            'Drafts and submits saved offline are uploaded when the device is online.',
            style: TextStyle(color: SeasColors.ink400, fontSize: 12.5, height: 1.4),
          ),
        ],
      ),
    );
  }
}

class HelpSupportScreen extends StatelessWidget {
  const HelpSupportScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Support',
      title: 'Help & support',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          const SeasCard(
            child: Column(
              children: [
                _ProfileMetaRow(Icons.mail_outline_rounded, 'Email', 'Mail@anujrathour.in'),
                Divider(height: 24),
                _ProfileMetaRow(Icons.phone_in_talk_outlined, 'Phone', '+91 82230 32232'),
                Divider(height: 24),
                _ProfileMetaRow(Icons.schedule_rounded, 'Hours', 'Mon–Sat · 9:00–18:00 IST'),
              ],
            ),
          ),
          const SizedBox(height: 14),
          SeasCard(
            child: Text(
              'Field tips\n'
              '• Save drafts when network is weak — sync later from Offline / sync.\n'
              '• Capture clear DTR & meter photos before submit.\n'
              '• Rejected surveys reopen for edit from Notifications or My surveys.',
              style: GoogleFonts.plusJakartaSans(height: 1.45, fontWeight: FontWeight.w500, color: SeasColors.ink800, fontSize: 13.5),
            ),
          ),
        ],
      ),
    );
  }
}

class AboutSeasScreen extends StatelessWidget {
  const AboutSeasScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'SEAS',
      title: 'About',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          SeasCard(
            child: Column(
              children: [
                const SeasLogoMark(size: 56),
                const SizedBox(height: 12),
                Text('Smart Energy Audit System', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
                const SizedBox(height: 4),
                const Text('Feeder → DTR → Consumer field audits', style: TextStyle(color: SeasColors.ink400, fontSize: 13)),
                const SizedBox(height: 16),
                const Divider(height: 1),
                const SizedBox(height: 16),
                const _ProfileMetaRow(Icons.smartphone_rounded, 'App version', '1.0.0+1'),
                const Divider(height: 24),
                const _ProfileMetaRow(Icons.branding_watermark_outlined, 'Brand', 'Srihari Energy · SEAS'),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileMetaRow extends StatelessWidget {
  const _ProfileMetaRow(this.icon, this.label, this.value);
  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        SeasIconTile(icon: icon, bg: SeasColors.canvas, fg: SeasColors.ink900),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(color: SeasColors.ink400, fontSize: 12, fontWeight: FontWeight.w600)),
              Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ],
    );
  }
}
