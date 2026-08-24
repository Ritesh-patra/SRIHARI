import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import '../core/api_client.dart';
import '../core/api_config.dart';
import '../core/hierarchy_cache.dart';
import '../core/offline_queue.dart';
import '../core/sync_service.dart';
import '../main.dart';
import '../theme/seas_colors.dart';
import '../widgets/seas_select.dart';
import '../widgets/seas_widgets.dart';
import '../widgets/power_line_animation.dart';
import 'dtr_survey_form.dart';
import 'dtr_consumer_hub_screen.dart';
import 'feeder_dtr_hub_screen.dart';
import 'login_screen.dart';
import 'consumer_approval_screen.dart';
import 'manager_hub.dart';
import 'team_audit_screen.dart';
import 'notifications_screen.dart';
import 'pole_selection_screen.dart';
import 'profile_hub.dart';
import 'my_progress_screen.dart';
import 'substation_survey_form.dart';
import '../core/seas_date_range.dart';
import '../core/file_download.dart';

// #region agent log
void _dbgLog(String location, String message, Map<String, dynamic> data, {String hypothesisId = 'A', String runId = 'pre-fix'}) {
  http
      .post(
        Uri.parse('http://127.0.0.1:7880/ingest/462b9acf-aae8-43ed-8500-97bbe6dedf80'),
        headers: {'Content-Type': 'application/json', 'X-Debug-Session-Id': 'a2382b'},
        body: jsonEncode({
          'sessionId': 'a2382b',
          'runId': runId,
          'hypothesisId': hypothesisId,
          'location': location,
          'message': message,
          'data': data,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        }),
      )
      .catchError((_) => http.Response('', 500));
}
// #endregion

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int tab = 0;
  Map<String, dynamic>? user;
  Map<String, dynamic>? dash;
  String? error;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    _boot();
  }

  bool get isSuperAdmin {
    final role = user?['role'] as String? ?? '';
    return role == 'super_admin';
  }

  /// Manager hub + approvals (Super Admin gets full manager control).
  bool get isManager {
    final role = user?['role'] as String? ?? '';
    return role == 'manager' || role == 'project_manager' || role == 'super_admin';
  }

  bool get canConsumerApprove {
    // Managers / PMs / Super Admin always; also honor API flag for admin / legacy.
    if (isSuperAdmin || isManager) return true;
    final role = user?['role'] as String? ?? '';
    if (role == 'admin') return true;
    final fromUser = user?['can_consumer_survey_approve'] == true;
    final fromDash = dash?['can_consumer_survey_approve'] == true;
    return fromUser || fromDash;
  }

  /// Field capture flows (Super Admin can also run FE surveys).
  bool get isFe {
    final role = user?['role'] as String? ?? '';
    return role == 'field_executive' || role == 'surveyor' || role == 'super_admin';
  }

  Future<void> _boot() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      user = await loadSavedUser();
      try {
        await hierarchyCache.ensureLoaded();
        await syncService.syncPending();
      } catch (_) {}
      dash = await api.get('/dashboard');
      try {
        final me = await api.get('/me');
        if (me['user'] is Map) user = Map<String, dynamic>.from(me['user'] as Map);
      } catch (_) {}
    } catch (e) {
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _openDtrSurvey() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const DtrSurveyFormScreen(autofetch: false)),
    );
    if (changed == true) _boot();
  }

  /// Substation Survey / Audit entry.
  Future<void> _openSubstationSurvey() async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const SubstationSurveyFormScreen()),
    );
    if (changed == true) _boot();
  }

  /// Feeder → DTR entry: options hub first, then navigate by choice.
  Future<void> _openFeederDtrHub() async {
    final name = user?['name']?.toString();
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => FeederDtrHubScreen(userName: name),
      ),
    );
    if (changed == true) _boot();
  }

  /// DTR → Consumer entry: Consumer / standalone DTR / status hub.
  Future<void> _openDtrConsumerHub() async {
    final name = user?['name']?.toString();
    final result = await Navigator.of(context).push<Object?>(
      MaterialPageRoute(
        builder: (_) => DtrConsumerHubScreen(
          userName: name,
          onOpenConsumer: () {
            Navigator.of(context).pop('consumer');
          },
        ),
      ),
    );
    if (!mounted) return;
    if (result == 'consumer') {
      setState(() => tab = 2);
    } else if (result == true) {
      _boot();
    }
  }

  Future<void> _logout() async {
    await api.logout();
    if (!mounted) return;
    // Wipe the full stack (home + any sheets/dialogs) so back never returns here.
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  Future<void> _onUserChanged(Map<String, dynamic>? next) async {
    if (next != null) {
      await saveSavedUser(next);
      if (mounted) setState(() => user = next);
      return;
    }
    try {
      final me = await api.refreshUser();
      if (me != null && mounted) setState(() => user = me);
    } catch (_) {}
  }

  Future<void> _openMenu() {
    return showProfileMenuSheet(
      context: context,
      user: user,
      isManager: isManager,
      onJump: (i) => setState(() => tab = i),
      onLogout: _logout,
      onUserChanged: _onUserChanged,
    );
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      _DashboardTab(
        user: user,
        dash: dash,
        loading: loading,
        error: error,
        onRefresh: _boot,
        isManager: isManager,
        isSuperAdmin: isSuperAdmin,
        canConsumerApprove: canConsumerApprove,
        onJump: (i) => setState(() => tab = i),
        onOpenMenu: _openMenu,
        onFeederDtr: _openFeederDtrHub,
        onDtrConsumer: _openDtrConsumerHub,
        onSubstationSurvey: _openSubstationSurvey,
        onConsumerApprove: () {
          Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ConsumerApprovalScreen()));
        },
        onTeamAudit: () {
          Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TeamAuditScreen()));
        },
      ),
      if (isManager) const _ApprovalsTab() else _SurveysTab(onNewSurvey: _openDtrSurvey),
      if (isManager) const ManagerHubTab() else const _ConsumerTab(),
      _ProfileTab(
        user: user,
        isManager: isManager,
        onLogout: _logout,
        onJump: (i) => setState(() => tab = i),
        onUserChanged: _onUserChanged,
      ),
    ];

    return Scaffold(
      backgroundColor: SeasColors.canvas,
      body: IndexedStack(index: tab.clamp(0, pages.length - 1), children: pages),
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: SeasColors.white,
          border: Border(top: BorderSide(color: Color(0x14000000))),
          boxShadow: [BoxShadow(color: Color(0x0F0F172A), blurRadius: 24, offset: Offset(0, -4))],
        ),
        child: NavigationBar(
          selectedIndex: tab,
          onDestinationSelected: (i) => setState(() => tab = i),
          destinations: [
            const NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home_rounded), label: 'Home'),
            if (isManager)
              const NavigationDestination(icon: Icon(Icons.fact_check_outlined), selectedIcon: Icon(Icons.fact_check_rounded), label: 'Approve')
            else
              const NavigationDestination(icon: Icon(Icons.bolt_outlined), selectedIcon: Icon(Icons.bolt_rounded), label: 'Surveys'),
            if (isManager)
              const NavigationDestination(icon: Icon(Icons.groups_outlined), selectedIcon: Icon(Icons.groups_rounded), label: 'Team')
            else
              const NavigationDestination(icon: Icon(Icons.people_outline), selectedIcon: Icon(Icons.people_rounded), label: 'Consumer'),
            const NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person_rounded), label: 'Profile'),
          ],
        ),
      ),
    );
  }
}

class _DashboardTab extends StatelessWidget {
  const _DashboardTab({
    required this.user,
    required this.dash,
    required this.loading,
    required this.error,
    required this.onRefresh,
    required this.isManager,
    required this.isSuperAdmin,
    required this.canConsumerApprove,
    required this.onJump,
    required this.onOpenMenu,
    required this.onFeederDtr,
    required this.onDtrConsumer,
    required this.onSubstationSurvey,
    required this.onConsumerApprove,
    required this.onTeamAudit,
  });
  final Map<String, dynamic>? user;
  final Map<String, dynamic>? dash;
  final bool loading;
  final String? error;
  final Future<void> Function() onRefresh;
  final bool isManager;
  final bool isSuperAdmin;
  final bool canConsumerApprove;
  final ValueChanged<int> onJump;
  final VoidCallback onOpenMenu;
  final VoidCallback onFeederDtr;
  final VoidCallback onDtrConsumer;
  final VoidCallback onSubstationSurvey;
  final VoidCallback onConsumerApprove;
  final VoidCallback onTeamAudit;

  @override
  Widget build(BuildContext context) {
    final stats = (dash?['stats'] as Map?) ?? {};
    final fullName = user?['name']?.toString() ?? 'User';
    final roleLabel = (user?['role_label'] ?? (isManager ? 'Manager' : 'Field Executive')).toString();
    // Avoid "Field Executive Field Executive Anuj"
    final welcomeName = fullName.toLowerCase().startsWith(roleLabel.toLowerCase())
        ? fullName
        : '$roleLabel $fullName';
    final nameParts = fullName.trim().split(RegExp(r'\s+'));
    final initial = nameParts.isNotEmpty && nameParts.last.isNotEmpty ? nameParts.last[0].toUpperCase() : 'U';
    final unread = int.tryParse('${stats['unread'] ?? 0}') ?? 0;

    // Super Admin: manager hub + FE survey entry points.
    final modules = <_HomeActionData>[
      if (!isManager || isSuperAdmin) ...[
        _HomeActionData(
          step: isSuperAdmin ? 'FE1' : '01',
          title: 'Substation Survey',
          subtitle: 'Substation Audit · GPS pin, Meter & Photos',
          icon: Icons.factory_rounded,
          tone: _HomeActionTone.softGrey,
          onTap: onSubstationSurvey,
        ),
        _HomeActionData(
          step: isSuperAdmin ? 'FE2' : '02',
          title: 'Feeder → DTR Audit',
          subtitle: 'Verify Feeder, SLD and DTR Mapping',
          icon: Icons.electrical_services_rounded,
          tone: _HomeActionTone.softRed,
          onTap: onFeederDtr,
        ),
        _HomeActionData(
          step: isSuperAdmin ? 'FE3' : '03',
          title: 'DTR → Consumer Audit',
          subtitle: 'Survey Consumers, Verify & Update Mapping',
          icon: Icons.people_rounded,
          tone: _HomeActionTone.softInk,
          onTap: onDtrConsumer,
        ),
        if (!isSuperAdmin)
          _HomeActionData(
            step: '04',
            title: 'My Progress',
            subtitle: 'Check Your Survey Progress and Performance',
            icon: Icons.insights_rounded,
            tone: _HomeActionTone.softWhite,
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const MyProgressFilterScreen()),
              );
            },
          ),
      ],
      if (isManager) ...[
        _HomeActionData(
          step: isSuperAdmin ? 'M1' : '01',
          title: 'Assign Work',
          subtitle: 'Send date-wise jobs to Field Executives',
          icon: Icons.person_add_alt_1_rounded,
          tone: isSuperAdmin ? _HomeActionTone.softGrey : _HomeActionTone.softRed,
          onTap: () => onJump(2),
        ),
        _HomeActionData(
          step: isSuperAdmin ? 'M2' : '02',
          title: 'Approvals',
          subtitle: 'Review audit · Approve or reject',
          icon: Icons.fact_check_rounded,
          tone: _HomeActionTone.softInk,
          onTap: () => onJump(1),
        ),
        _HomeActionData(
          step: isSuperAdmin ? 'M3' : '03',
          title: 'Team Activity',
          subtitle: 'Per FE: feeder / DTR / consumer lists',
          icon: Icons.assessment_rounded,
          tone: _HomeActionTone.softWhite,
          onTap: onTeamAudit,
        ),
        if (canConsumerApprove)
          _HomeActionData(
            step: isSuperAdmin ? 'M4' : '04',
            title: 'Consumer Approval',
            subtitle: 'Approve / reject consumer surveys · meter photo',
            icon: Icons.how_to_reg_rounded,
            tone: _HomeActionTone.softGrey,
            onTap: onConsumerApprove,
          ),
        _HomeActionData(
          step: isSuperAdmin ? (canConsumerApprove ? 'M5' : 'M4') : (canConsumerApprove ? '05' : '04'),
          title: 'Assignments',
          subtitle: 'Zone / feeder work for field executives',
          icon: Icons.timeline_rounded,
          tone: _HomeActionTone.softGrey,
          onTap: () => onJump(2),
        ),
        if (!isSuperAdmin)
          _HomeActionData(
            step: canConsumerApprove ? '06' : '05',
            title: 'Progress',
            subtitle: 'Pending approvals & pipeline',
            icon: Icons.insights_rounded,
            tone: _HomeActionTone.softWhite,
            onTap: () => onJump(1),
          ),
      ],
    ];

    return RefreshIndicator(
      color: SeasColors.volt,
      onRefresh: onRefresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          // Brand header — towers with live current on the wire
          SliverToBoxAdapter(
            child: Container(
              width: double.infinity,
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFFFFF5F4), Color(0xFFFFFFFF), Color(0xFFF0F2F5)],
                ),
              ),
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 10, 18, 8),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Column(
                            children: [
                              const SeasLogoMark(size: 48),
                              const SizedBox(height: 4),
                              Text(
                                'SEAS',
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1.2,
                                  color: SeasColors.ink900,
                                ),
                              ),
                            ],
                          ),
                          const Spacer(),
                          _HeaderIconBtn(
                            icon: Icons.notifications_none_rounded,
                            badge: unread > 0 ? (unread > 9 ? '9+' : '$unread') : null,
                            onTap: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => const NotificationsScreen()),
                              );
                              onRefresh();
                            },
                          ),
                          const SizedBox(width: 8),
                          _HeaderIconBtn(
                            icon: Icons.menu_rounded,
                            onTap: onOpenMenu,
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Text(
                        'SMART ENERGY\nAUDIT SYSTEM',
                        textAlign: TextAlign.center,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 28,
                          fontWeight: FontWeight.w800,
                          height: 1.08,
                          letterSpacing: -0.8,
                          color: SeasColors.ink950,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Feeder → DTR → Consumer Audit',
                        textAlign: TextAlign.center,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: SeasColors.ink400,
                        ),
                      ),
                      const SizedBox(height: 4),
                      const PowerLineAnimation(height: 96),
                    ],
                  ),
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Container(
              width: double.infinity,
              decoration: const BoxDecoration(
                color: SeasColors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (error != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 14),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: SeasColors.voltSoft, borderRadius: BorderRadius.circular(14)),
                      child: Text(error!, style: const TextStyle(color: SeasColors.voltDeep, fontWeight: FontWeight.w600, fontSize: 13)),
                    ),
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Welcome,', style: TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 2),
                            Text(
                              welcomeName,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.4,
                                color: SeasColors.ink950,
                                height: 1.15,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              isSuperAdmin
                                  ? 'Full access · Manager hub · Field surveys'
                                  : isManager
                                      ? 'Assign work · Approve surveys · Track activity'
                                      : 'Feeder → DTR → Consumer Audit',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: SeasColors.ink400, fontSize: 12, height: 1.3),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      Builder(builder: (_) {
                        final avatarUrl = ApiConfig.mediaUrl(
                          user?['avatar_url']?.toString() ?? user?['avatar']?.toString(),
                        );
                        return Container(
                          height: 68,
                          width: 68,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: SeasColors.white,
                            border: Border.all(color: SeasColors.volt, width: 2.5),
                            boxShadow: SeasShadows.card,
                            image: avatarUrl == null
                                ? null
                                : DecorationImage(image: NetworkImage(avatarUrl), fit: BoxFit.cover),
                          ),
                          alignment: Alignment.center,
                          child: avatarUrl == null
                              ? Text(
                                  initial,
                                  style: GoogleFonts.plusJakartaSans(fontSize: 26, fontWeight: FontWeight.w800, color: SeasColors.volt),
                                )
                              : null,
                        );
                      }),
                    ],
                  ),
                  const SizedBox(height: 20),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 14,
                    crossAxisSpacing: 14,
                    childAspectRatio: 0.92,
                    children: modules.map((m) => _HomeActionCard(data: m)).toList(),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton(
                          onPressed: isSuperAdmin || !isManager ? onFeederDtr : () => onJump(2),
                          style: FilledButton.styleFrom(
                            backgroundColor: SeasColors.volt,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            elevation: 0,
                          ),
                          child: Text(
                            isSuperAdmin || !isManager ? 'Feeder → DTR Audit' : 'Assign Work',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: isSuperAdmin
                              ? () => onJump(1)
                              : isManager
                                  ? () => onJump(1)
                                  : onDtrConsumer,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: SeasColors.ink950,
                            side: const BorderSide(color: SeasColors.ink900, width: 1.2),
                            backgroundColor: SeasColors.white,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                          child: Text(
                            isSuperAdmin || isManager ? 'Approvals' : 'DTR → Consumer',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 28),
                  Text(
                    'PROGRESS',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.6,
                      color: SeasColors.volt,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (loading)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 16),
                      child: LinearProgressIndicator(minHeight: 3, color: SeasColors.volt, backgroundColor: SeasColors.ink100),
                    )
                  else
                    _HomeSurveyProgress(stats: stats),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

enum _HomeActionTone { softRed, softInk, softGrey, softWhite }

class _HomeActionData {
  const _HomeActionData({
    required this.step,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.tone,
    required this.onTap,
  });
  final String step;
  final String title;
  final String subtitle;
  final IconData icon;
  final _HomeActionTone tone;
  final VoidCallback onTap;
}

class _HomeActionCard extends StatelessWidget {
  const _HomeActionCard({required this.data});
  final _HomeActionData data;

  @override
  Widget build(BuildContext context) {
    late Color bg;
    late Color accent;
    late Color iconBg;
    switch (data.tone) {
      case _HomeActionTone.softRed:
        bg = SeasColors.voltSoft;
        accent = SeasColors.volt;
        iconBg = SeasColors.white;
      case _HomeActionTone.softInk:
        bg = const Color(0xFFF3F4F6);
        accent = SeasColors.ink950;
        iconBg = SeasColors.white;
      case _HomeActionTone.softGrey:
        bg = const Color(0xFFF7F7F8);
        accent = SeasColors.ink800;
        iconBg = SeasColors.white;
      case _HomeActionTone.softWhite:
        bg = SeasColors.white;
        accent = SeasColors.volt;
        iconBg = SeasColors.voltSoft;
    }

    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(22),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: data.onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: accent.withValues(alpha: data.tone == _HomeActionTone.softWhite ? 0.22 : 0.10)),
            boxShadow: [
              BoxShadow(color: Colors.black.withValues(alpha: 0.045), blurRadius: 18, offset: const Offset(0, 8)),
            ],
          ),
          child: Stack(
            children: [
              Positioned(
                left: 0,
                top: 18,
                bottom: 18,
                child: Container(
                  width: 3.5,
                  decoration: BoxDecoration(
                    color: accent,
                    borderRadius: const BorderRadius.horizontal(right: Radius.circular(4)),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 14, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          height: 40,
                          width: 40,
                          decoration: BoxDecoration(
                            color: iconBg,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: accent.withValues(alpha: 0.12)),
                          ),
                          child: Icon(data.icon, color: accent, size: 22),
                        ),
                        const Spacer(),
                        Text(
                          data.step,
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                            color: accent.withValues(alpha: 0.55),
                          ),
                        ),
                      ],
                    ),
                    const Spacer(),
                    Text(
                      data.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 13.5,
                        height: 1.18,
                        color: SeasColors.ink950,
                        letterSpacing: -0.2,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      data.subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: SeasColors.ink400, fontSize: 11, height: 1.28),
                    ),
                    const SizedBox(height: 12),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Container(
                        height: 32,
                        width: 32,
                        decoration: BoxDecoration(
                          color: accent,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 17),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Progress from `/dashboard` stats (no new backend fields required for DTR/Consumer).
///
/// Feeder Surveys:
///   done  = pending_approval + approved + completed  (submitted feeder surveys)
///   total = pending + rejected + approved + completed
///
/// DTR Surveys:
///   done  = approved + completed  (DTR stage cleared)
///   total = pending + rejected + approved + completed
///
/// Consumer Surveys:
///   done  = completed             (consumer_survey_completed_at set)
///   total = approved + completed  (eligible for / finished consumer audit)
class _HomeSurveyProgress extends StatelessWidget {
  const _HomeSurveyProgress({required this.stats});
  final Map stats;

  int _n(Object? v) => int.tryParse('$v') ?? 0;

  @override
  Widget build(BuildContext context) {
    final pending = _n(stats['pending']);
    final rejected = _n(stats['rejected']);
    final approved = _n(stats['approved']);
    final completed = _n(stats['completed']);

    final dtrDone = approved + completed;
    final dtrTotal = pending + rejected + approved + completed;
    final consumerDone = completed;
    final consumerTotal = approved + completed;

    final feederDone = _n(stats['feeder_submitted']);
    final feederTotal = _n(stats['feeder_total']);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: SeasColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: SeasColors.ink100),
        boxShadow: SeasShadows.card,
      ),
      child: Column(
        children: [
          _SurveyProgressRow(
            label: 'Feeder Surveys',
            done: feederDone,
            total: feederTotal == 0 ? feederDone : feederTotal,
            accent: SeasColors.volt,
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Divider(height: 1, thickness: 1, color: SeasColors.ink100.withValues(alpha: 0.9)),
          ),
          _SurveyProgressRow(
            label: 'DTR Surveys',
            done: dtrDone,
            total: dtrTotal,
            accent: SeasColors.ink950,
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Divider(height: 1, thickness: 1, color: SeasColors.ink100.withValues(alpha: 0.9)),
          ),
          _SurveyProgressRow(
            label: 'Consumer Surveys',
            done: consumerDone,
            total: consumerTotal,
            accent: SeasColors.voltDeep,
          ),
        ],
      ),
    );
  }
}

class _SurveyProgressRow extends StatelessWidget {
  const _SurveyProgressRow({
    required this.label,
    required this.done,
    required this.total,
    required this.accent,
  });

  final String label;
  final int done;
  final int total;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final ratio = total <= 0 ? 0.0 : (done / total).clamp(0.0, 1.0);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                  color: SeasColors.ink900,
                ),
              ),
            ),
            Text(
              '$done of $total done',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: accent,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            value: ratio,
            minHeight: 6,
            color: accent,
            backgroundColor: SeasColors.ink100,
          ),
        ),
      ],
    );
  }
}

class _HeaderIconBtn extends StatelessWidget {
  const _HeaderIconBtn({required this.icon, required this.onTap, this.badge});
  final IconData icon;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Ink(
          height: 42,
          width: 42,
          decoration: BoxDecoration(
            color: SeasColors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: SeasColors.ink200),
            boxShadow: SeasShadows.card,
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              Icon(icon, color: SeasColors.ink900),
              if (badge != null)
                Positioned(
                  right: 4,
                  top: 4,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                    decoration: const BoxDecoration(color: SeasColors.volt, borderRadius: BorderRadius.all(Radius.circular(8))),
                    constraints: const BoxConstraints(minWidth: 16),
                    child: Text(
                      badge!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SurveysTab extends StatefulWidget {
  const _SurveysTab({required this.onNewSurvey});
  final VoidCallback onNewSurvey;
  @override
  State<_SurveysTab> createState() => _SurveysTabState();
}

class _SurveysTabState extends State<_SurveysTab> {
  List items = [];
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
      await syncService.syncPending();
      offline = await offlineQueue.all();
      final res = await api.get('/surveys');
      items = (res['data'] as List?) ?? [];
    } catch (e) {
      offline = await offlineQueue.all();
      error = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final empty = items.isEmpty && offline.isEmpty;
    return SeasPageScaffold(
      eyebrow: 'Field work',
      title: 'My DTR Surveys',
      actions: [
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
        IconButton(onPressed: widget.onNewSurvey, icon: const Icon(Icons.add_circle_outline, color: SeasColors.volt)),
      ],
      floatingActionButton: FloatingActionButton.extended(
        onPressed: widget.onNewSurvey,
        backgroundColor: SeasColors.volt,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.bolt_rounded),
        label: const Text('DTR Survey'),
      ),
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : empty
              ? SeasEmptyState(
                  title: error != null ? 'Offline / no server list' : 'No surveys yet',
                  subtitle: error ?? 'Tap + DTR Survey to start capturing.',
                  icon: Icons.bolt_outlined,
                )
              : RefreshIndicator(
                  color: SeasColors.volt,
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                    children: [
                      if (offline.isNotEmpty) ...[
                        Text('Pending sync (${offline.length})', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
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
                                          Text('Will sync when internet is available', style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
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
                      if (items.isNotEmpty)
                        Text('Server surveys', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      ...items.map((raw) {
                        final s = raw as Map;
                        final status = '${s['status'] ?? ''}';
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: SeasCard(
                            padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                            onTap: () async {
                              if (status == 'draft' || status == 'rejected') {
                                await Navigator.of(context).push(MaterialPageRoute(
                                  builder: (_) => DtrSurveyFormScreen(serverId: s['id'] as int?),
                                ));
                                _load();
                              }
                            },
                            child: Row(
                              children: [
                                SeasIconTile(icon: Icons.electrical_services_rounded, bg: SeasColors.voltSoft, fg: SeasColors.volt),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('${s['dtr_name'] ?? 'DTR'}', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                                      const SizedBox(height: 2),
                                      Text('${s['feeder_name'] ?? 'Feeder'}', style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                                    ],
                                  ),
                                ),
                                SeasBadge(status.isEmpty ? '—' : status, tone: badgeToneForStatus(status)),
                                const SizedBox(width: 4),
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

class _ApprovalsTab extends StatefulWidget {
  const _ApprovalsTab();
  @override
  State<_ApprovalsTab> createState() => _ApprovalsTabState();
}

class _ApprovalsTabState extends State<_ApprovalsTab> {
  List items = [];
  Map summary = {};
  bool loading = true;
  String filter = 'all'; // all | feeder | dtr | consumer
  bool exporting = false;
  late DateTime from;
  late DateTime to;

  @override
  void initState() {
    super.initState();
    to = DateTime.now();
    from = to.subtract(const Duration(days: 30));
    _load();
  }

  String _fmt(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await api.get('/approvals?type=$filter');
      items = (res['data'] as List?) ?? [];
      summary = Map<String, dynamic>.from((res['summary'] as Map?) ?? {});
    } catch (_) {
      items = [];
    }
    if (mounted) setState(() => loading = false);
  }

  void _setFilter(String next) {
    if (filter == next) return;
    setState(() => filter = next);
    _load();
  }

  Future<void> _downloadReport() async {
    final picked = await pickSeasDateRange(
      context,
      initial: DateTimeRange(start: from, end: to),
      helpText: 'Report date range',
    );
    if (picked == null) return;
    setState(() {
      from = picked.start;
      to = picked.end;
      exporting = true;
    });
    try {
      final file = await api.getBytes('/team-audit/export?from=${_fmt(from)}&to=${_fmt(to)}');
      final saved = await saveDownloadBytes(file.bytes, file.filename);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(saved == null ? 'Excel downloaded' : 'Excel saved: $saved'),
          backgroundColor: SeasColors.ink950,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => exporting = false);
    }
  }

  Future<void> _quickApprove(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null) return;
    final type = '${s['type'] ?? 'dtr'}';
    final title = '${s['title'] ?? s['dtr_name'] ?? 'survey'}';
    final typeLabel = type == 'feeder' ? 'Feeder' : type == 'consumer' ? 'Consumer' : 'DTR';

    // Feeder items in sld_pending cannot use approve API yet.
    if (type == 'feeder' && '${s['status']}' != 'pending_approval') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Open Review — SLD / status not ready for quick approve')),
      );
      return;
    }

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Approve $typeLabel?'),
        content: Text('Approve "$title" without opening full review?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: SeasColors.success),
            child: const Text('Approve'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    try {
      if (type == 'feeder') {
        await api.post('/feeder-surveys/$id/approve', {'review_remarks': ''});
      } else if (type == 'consumer') {
        await api.post('/consumer-surveys/bulk-action', {
          'ids': [id],
          'action': 'approve',
        });
      } else {
        await api.post('/surveys/$id/approve', {'review_remarks': ''});
      }
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$typeLabel approved'), backgroundColor: SeasColors.ink950),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  String _prettyDate(dynamic raw) {
    if (raw == null) return '—';
    final s = raw.toString();
    if (s.length >= 10) {
      final p = s.substring(0, 10).split('-');
      if (p.length == 3) return '${p[2]}/${p[1]}/${p[0]}';
    }
    return s;
  }

  Future<void> _review(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null) return;
    final type = '${s['type'] ?? 'dtr'}';

    if (type == 'consumer') {
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ConsumerApprovalScreen()));
      await _load();
      return;
    }

    if (type == 'feeder') {
      // Reuse Manager hub feeder review via deep link into Team tab is heavy;
      // call feeder detail + approve/reject sheet inline.
      await _reviewFeederInline(s);
      return;
    }

    Map detail = Map<String, dynamic>.from(s);
    bool canApprove = true;
    bool canEdit = true;
    bool canDelete = true;
    String? loadError;
    try {
      final res = await api.get('/surveys/$id');
      if (res['survey'] is Map) {
        detail = Map<String, dynamic>.from(res['survey'] as Map);
      }
      canApprove = res['can_approve'] == true;
      canEdit = res['can_edit'] != false;
      canDelete = res['can_delete'] != false;
    } catch (e) {
      loadError = e.toString().replaceFirst('Exception: ', '');
    }
    if (!mounted) return;

    final remarks = TextEditingController();
    String? remarkError;
    final status = '${detail['status'] ?? ''}';
    final statusLabel = '${detail['status_label'] ?? s['status_label'] ?? _humanStatus(status)}';
    String? photoUrl(dynamic v) {
      if (v == null) return null;
      final s = v.toString().trim();
      if (s.isEmpty) return null;
      return ApiConfig.mediaUrl(s);
    }

    final overallUrl = photoUrl(detail['dtr_overall_photo_url'] ?? detail['dtr_overall_photo']);
    final meterUrl = photoUrl(detail['smart_meter_photo_url'] ?? detail['smart_meter_photo']);
    final ctUrl = photoUrl(detail['ct_ratio_photo_url'] ?? detail['ct_ratio_photo']);

    Widget thumb(String? url, String label) {
      return SizedBox(
        width: 130,
        height: 140,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Material(
                color: SeasColors.canvasSoft,
                borderRadius: BorderRadius.circular(12),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: url == null
                      ? null
                      : () => showDialog<void>(
                            context: context,
                            builder: (dctx) => Dialog(
                              backgroundColor: Colors.black,
                              child: InteractiveViewer(
                                child: Image.network(url, fit: BoxFit.contain, errorBuilder: (_, __, ___) {
                                  return const Center(child: Icon(Icons.broken_image_outlined, color: Colors.white54, size: 48));
                                }),
                              ),
                            ),
                          ),
                  child: url == null
                      ? const Center(child: Icon(Icons.image_not_supported_outlined, color: SeasColors.ink400))
                      : Image.network(
                          url,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          height: double.infinity,
                          errorBuilder: (_, __, ___) => const Center(child: Icon(Icons.broken_image_outlined, color: SeasColors.ink400)),
                        ),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
          ],
        ),
      );
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setSheet) {
          return DraggableScrollableSheet(
            initialChildSize: 0.9,
            minChildSize: 0.5,
            maxChildSize: 0.98,
            expand: false,
            builder: (ctx, scrollCtrl) {
              return Container(
                decoration: const BoxDecoration(
                  color: SeasColors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: ListView(
                  controller: scrollCtrl,
                  padding: EdgeInsets.fromLTRB(20, 12, 20, 24 + MediaQuery.viewInsetsOf(ctx).bottom),
                  children: [
                    Center(child: Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99)))),
                    const SizedBox(height: 14),
                    Text('${detail['dtr_name'] ?? 'DTR'}', style: GoogleFonts.plusJakartaSans(fontSize: 22, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text('DTR · ${detail['dtr_code'] ?? ''} · ${detail['feeder_name'] ?? ''}', style: const TextStyle(color: SeasColors.ink400, fontSize: 13)),
                    if (loadError != null) ...[
                      const SizedBox(height: 10),
                      Text(loadError!, style: const TextStyle(color: SeasColors.volt, fontSize: 12)),
                    ],
                    const SizedBox(height: 14),
                    _meta('Surveyor', '${detail['surveyor']?['name'] ?? '—'}'),
                    _meta('Status', statusLabel),
                    _meta('Region', '${detail['region']?['name'] ?? '—'}'),
                    _meta('Circle', '${detail['circle']?['name'] ?? '—'}'),
                    _meta('Division', '${detail['division']?['name'] ?? '—'}'),
                    _meta('Zone', '${detail['zone']?['name'] ?? '—'}'),
                    _meta('Substation', '${detail['substation']?['name'] ?? '—'}'),
                    _meta('Capacity', '${detail['dtr_capacity_kva'] ?? '—'} kVA'),
                    _meta('Condition', '${detail['dtr_condition'] ?? '—'}'),
                    _meta('LT Line Type', '${detail['lt_line_type'] ?? '—'}'),
                    _meta('Smart meter', '${detail['smart_meter_status'] ?? '—'}'),
                    _meta('Old meter condition', '${detail['old_meter_condition'] ?? '—'}'),
                    _meta('Old MSN', '${detail['old_msn'] ?? '—'}'),
                    _meta('Old make', '${detail['old_meter_make'] ?? '—'}'),
                    _meta('New MSN', '${detail['new_msn'] ?? '—'}'),
                    _meta('New make', '${detail['new_meter_make'] ?? '—'}'),
                    _meta('CT ratio', '${detail['new_meter_ct_ratio'] ?? '—'}'),
                    _meta('MF', '${detail['new_meter_mf'] ?? '—'}'),
                    _meta('GPS', '${detail['latitude'] ?? '—'}, ${detail['longitude'] ?? '—'}'),
                    _meta('Observation', '${detail['observation'] ?? '—'}'),
                    _meta('Surveyed', _prettyDate(detail['surveyed_at'])),
                    const SizedBox(height: 14),
                    Text('Photos', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 148,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          if (overallUrl != null) ...[thumb(overallUrl, 'DTR overall'), const SizedBox(width: 10)],
                          if (meterUrl != null) ...[thumb(meterUrl, 'Smart meter'), const SizedBox(width: 10)],
                          if (ctUrl != null) thumb(ctUrl, 'CT ratio'),
                          if (overallUrl == null && meterUrl == null && ctUrl == null)
                            const Padding(
                              padding: EdgeInsets.only(top: 48),
                              child: Text('No photos uploaded.', style: TextStyle(color: SeasColors.ink400, fontSize: 13)),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        if (canEdit)
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () async {
                                String? condition = detail['dtr_condition']?.toString();
                                String? smartStatus = detail['smart_meter_status']?.toString();
                                final newMsn = TextEditingController(text: '${detail['new_msn'] ?? ''}');
                                final observation = TextEditingController(text: '${detail['observation'] ?? ''}');
                                final saved = await showModalBottomSheet<bool>(
                                  context: ctx,
                                  isScrollControlled: true,
                                  backgroundColor: Colors.transparent,
                                  builder: (ectx) {
                                    return StatefulBuilder(builder: (ectx, setModal) {
                                      return Padding(
                                        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ectx).bottom),
                                        child: Container(
                                          decoration: const BoxDecoration(
                                            color: SeasColors.white,
                                            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                                          ),
                                          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                                          child: Column(
                                            mainAxisSize: MainAxisSize.min,
                                            crossAxisAlignment: CrossAxisAlignment.stretch,
                                            children: [
                                              Text('Edit DTR survey', style: GoogleFonts.plusJakartaSans(fontSize: 20, fontWeight: FontWeight.w800)),
                                              const SizedBox(height: 12),
                                              SeasSelectField(
                                                label: 'Condition',
                                                hint: 'Select',
                                                value: condition,
                                                options: ['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other']
                                                    .map((e) => SeasSelectOption(value: e, label: e))
                                                    .toList(),
                                                onSelected: (o) => setModal(() => condition = o.value as String?),
                                              ),
                                              const SizedBox(height: 10),
                                              SeasSelectField(
                                                label: 'Smart meter status',
                                                hint: 'Select',
                                                value: smartStatus,
                                                options: ['Installed', 'Not Installed', 'Meter Missing']
                                                    .map((e) => SeasSelectOption(value: e, label: e))
                                                    .toList(),
                                                onSelected: (o) => setModal(() => smartStatus = o.value as String?),
                                              ),
                                              const SizedBox(height: 10),
                                              SeasTextField(label: 'New MSN', controller: newMsn),
                                              const SizedBox(height: 10),
                                              SeasTextField(label: 'Observation', controller: observation, maxLines: 3),
                                              const SizedBox(height: 14),
                                              FilledButton(
                                                onPressed: () => Navigator.pop(ectx, true),
                                                style: FilledButton.styleFrom(backgroundColor: SeasColors.volt, padding: const EdgeInsets.symmetric(vertical: 14)),
                                                child: const Text('Save'),
                                              ),
                                            ],
                                          ),
                                        ),
                                      );
                                    });
                                  },
                                );
                                if (saved != true) return;
                                try {
                                  await api.put('/surveys/$id', {
                                    if (condition != null) 'dtr_condition': condition,
                                    if (smartStatus != null) 'smart_meter_status': smartStatus,
                                    'new_msn': newMsn.text.trim(),
                                    'observation': observation.text.trim(),
                                  });
                                  if (ctx.mounted) Navigator.pop(ctx);
                                  await _load();
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('DTR survey updated'), backgroundColor: SeasColors.ink950),
                                  );
                                } catch (e) {
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                                }
                              },
                              style: OutlinedButton.styleFrom(foregroundColor: SeasColors.volt, side: const BorderSide(color: SeasColors.volt)),
                              child: const Text('Edit'),
                            ),
                          ),
                        if (canEdit && canDelete) const SizedBox(width: 8),
                        if (canDelete)
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () async {
                                final ok = await showDialog<bool>(
                                  context: ctx,
                                  builder: (dctx) => AlertDialog(
                                    title: const Text('Delete DTR survey?'),
                                    content: Text('Permanently delete ${detail['dtr_name'] ?? 'this survey'}?'),
                                    actions: [
                                      TextButton(onPressed: () => Navigator.pop(dctx, false), child: const Text('Cancel')),
                                      FilledButton(
                                        onPressed: () => Navigator.pop(dctx, true),
                                        style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
                                        child: const Text('Delete'),
                                      ),
                                    ],
                                  ),
                                );
                                if (ok != true) return;
                                try {
                                  await api.delete('/surveys/$id');
                                  if (ctx.mounted) Navigator.pop(ctx);
                                  await _load();
                                } catch (e) {
                                  if (!mounted) return;
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                                }
                              },
                              child: const Text('Delete'),
                            ),
                          ),
                      ],
                    ),
                    if (canApprove && status == 'pending_approval') ...[
                      const SizedBox(height: 18),
                      Text('Decision', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15)),
                      const SizedBox(height: 8),
                      SeasTextField(label: 'Remarks', controller: remarks, hint: 'Optional for approve · required for reject', maxLines: 3),
                      if (remarkError != null) ...[
                        const SizedBox(height: 6),
                        Text(remarkError!, style: const TextStyle(color: SeasColors.volt, fontWeight: FontWeight.w700, fontSize: 12.5)),
                      ],
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: () async {
                          try {
                            await api.post('/surveys/$id/approve', {'review_remarks': remarks.text.trim()});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.success, padding: const EdgeInsets.symmetric(vertical: 14)),
                        child: const Text('Approve'),
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: () async {
                          if (remarks.text.trim().isEmpty) {
                            setSheet(() => remarkError = 'Rejection remarks are mandatory. Enter a reason before rejecting.');
                            return;
                          }
                          setSheet(() => remarkError = null);
                          try {
                            await api.post('/surveys/$id/reject', {'review_remarks': remarks.text.trim()});
                            if (ctx.mounted) Navigator.pop(ctx);
                            await _load();
                          } catch (e) {
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                          }
                        },
                        style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950, padding: const EdgeInsets.symmetric(vertical: 14)),
                        child: const Text('Reject'),
                      ),
                    ] else ...[
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
                    ],
                  ],
                ),
              );
            },
          );
        });
      },
    );
  }

  Widget _meta(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 110, child: Text(label, style: const TextStyle(color: SeasColors.ink400, fontSize: 12.5))),
          Expanded(child: Text(value, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 13))),
        ],
      ),
    );
  }

  String _humanStatus(String status) {
    switch (status) {
      case 'pending_approval':
        return 'Pending Approval';
      case 'sld_pending':
        return 'SLD Verification Pending';
      case 'draft':
        return 'Draft';
      case 'approved':
        return 'Approved';
      case 'rejected':
        return 'Rejected';
      case 'completed':
        return 'Completed';
      default:
        return status.isEmpty ? '—' : status.replaceAll('_', ' ');
    }
  }

  Future<void> _reviewFeederInline(Map s) async {
    final id = (s['id'] as num?)?.toInt();
    if (id == null) return;
    Map detail = Map<String, dynamic>.from(s);
    bool canApprove = '${s['status']}' == 'pending_approval';
    String? loadError;
    try {
      final res = await api.get('/feeder-surveys/$id');
      if (res['survey'] is Map) detail = Map<String, dynamic>.from(res['survey'] as Map);
      canApprove = res['can_approve'] == true;
    } catch (e) {
      loadError = e.toString().replaceFirst('Exception: ', '');
    }
    if (!mounted) return;
    final remarks = TextEditingController();
    final status = '${detail['status'] ?? ''}';
    final statusLabel = '${detail['display_status'] ?? s['status_label'] ?? _humanStatus(status)}';

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: SeasColors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          padding: EdgeInsets.fromLTRB(20, 16, 20, 24 + MediaQuery.viewInsetsOf(ctx).bottom),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('${detail['feeder_name'] ?? 'Feeder'}', style: GoogleFonts.plusJakartaSans(fontSize: 20, fontWeight: FontWeight.w800)),
              Text('Feeder · ${detail['feeder_code'] ?? ''} · $statusLabel', style: const TextStyle(color: SeasColors.ink400, fontSize: 13)),
              if (loadError != null) ...[
                const SizedBox(height: 8),
                Text(loadError!, style: const TextStyle(color: SeasColors.volt, fontSize: 12)),
              ],
              const SizedBox(height: 12),
              _meta('Surveyor', '${detail['surveyor']?['name'] ?? s['surveyor']?['name'] ?? '—'}'),
              if (canApprove) ...[
                const SizedBox(height: 10),
                SeasTextField(label: 'Remarks', controller: remarks, hint: 'Optional for approve · required for reject', maxLines: 2),
                const SizedBox(height: 10),
                FilledButton(
                  onPressed: () async {
                    try {
                      await api.post('/feeder-surveys/$id/approve', {'review_remarks': remarks.text.trim()});
                      if (ctx.mounted) Navigator.pop(ctx);
                      await _load();
                    } catch (e) {
                      if (!mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                    }
                  },
                  style: FilledButton.styleFrom(backgroundColor: SeasColors.success),
                  child: const Text('Approve Feeder'),
                ),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: () async {
                    if (remarks.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Rejection remarks required')));
                      return;
                    }
                    try {
                      await api.post('/feeder-surveys/$id/reject', {'review_remarks': remarks.text.trim()});
                      if (ctx.mounted) Navigator.pop(ctx);
                      await _load();
                    } catch (e) {
                      if (!mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
                    }
                  },
                  style: FilledButton.styleFrom(backgroundColor: SeasColors.ink950),
                  child: const Text('Reject'),
                ),
              ] else
                OutlinedButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
            ],
          ),
        );
      },
    );
  }

  Widget _glassTile({
    required String keyName,
    required String label,
    required String value,
    required bool selected,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: () => _setFilter(keyName),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
          decoration: BoxDecoration(
            color: selected ? SeasColors.volt.withValues(alpha: 0.14) : Colors.white.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: selected ? SeasColors.volt.withValues(alpha: 0.55) : Colors.white.withValues(alpha: 0.75),
              width: selected ? 1.4 : 1,
            ),
            boxShadow: [
              BoxShadow(
                color: selected ? SeasColors.volt.withValues(alpha: 0.18) : const Color(0x14000000),
                blurRadius: selected ? 16 : 12,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: GoogleFonts.plusJakartaSans(
                  fontWeight: FontWeight.w800,
                  fontSize: 20,
                  color: selected ? SeasColors.volt : SeasColors.ink950,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: selected ? SeasColors.voltDeep : SeasColors.ink400,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  IconData _typeIcon(String type) {
    switch (type) {
      case 'feeder':
        return Icons.account_tree_outlined;
      case 'consumer':
        return Icons.person_outline_rounded;
      default:
        return Icons.electric_meter_outlined;
    }
  }

  @override
  Widget build(BuildContext context) {
    final pendingHint = filter == 'all'
        ? ((summary['feeder_pending'] ?? 0) as num).toInt() +
            ((summary['dtr_pending'] ?? 0) as num).toInt() +
            ((summary['consumer_pending'] ?? 0) as num).toInt()
        : filter == 'feeder'
            ? (summary['feeder_pending'] ?? 0)
            : filter == 'dtr'
                ? (summary['dtr_pending'] ?? 0)
                : (summary['consumer_pending'] ?? 0);

    return SeasPageScaffold(
      eyebrow: 'Review',
      title: 'Approvals',
      actions: [
        IconButton(
          tooltip: 'Download Excel report',
          onPressed: exporting ? null : _downloadReport,
          icon: exporting
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Icon(Icons.download_rounded),
        ),
      ],
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                children: [
                  Row(
                    children: [
                      _glassTile(keyName: 'all', label: 'Total', value: '${summary['total'] ?? 0}', selected: filter == 'all'),
                      const SizedBox(width: 8),
                      _glassTile(keyName: 'feeder', label: 'Feeder', value: '${summary['feeder'] ?? 0}', selected: filter == 'feeder'),
                      const SizedBox(width: 8),
                      _glassTile(keyName: 'dtr', label: 'DTR', value: '${summary['dtr'] ?? 0}', selected: filter == 'dtr'),
                      const SizedBox(width: 8),
                      _glassTile(keyName: 'consumer', label: 'Consumer', value: '${summary['consumer'] ?? 0}', selected: filter == 'consumer'),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Pending review · $pendingHint  ·  tap tiles to filter',
                    style: const TextStyle(color: SeasColors.ink400, fontSize: 12, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 14),
                  if (items.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 48),
                      child: SeasEmptyState(
                        title: 'Inbox clear',
                        subtitle: 'No surveys waiting for your approval in this filter.',
                        icon: Icons.verified_outlined,
                      ),
                    )
                  else
                    ...List.generate(items.length, (i) {
                      final s = items[i] as Map;
                      final type = '${s['type'] ?? 'dtr'}';
                      final title = '${s['title'] ?? s['dtr_name'] ?? 'Survey'}';
                      final subtitle = '${s['subtitle'] ?? s['feeder_name'] ?? ''}';
                      final rawStatus = '${s['status'] ?? ''}';
                      final statusLabel = '${s['status_label'] ?? _humanStatus(rawStatus)}';
                      final canQuickApprove = type == 'dtr' ||
                          (type == 'feeder' && rawStatus == 'pending_approval') ||
                          type == 'consumer';
                      return Padding(
                        padding: EdgeInsets.only(bottom: i == items.length - 1 ? 0 : 12),
                        child: SeasCard(
                          onTap: () => _review(s),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  SeasIconTile(icon: _typeIcon(type), bg: SeasColors.ink950),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(title, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 16)),
                                        const SizedBox(height: 2),
                                        Text(
                                          '${s['type_label'] ?? type.toUpperCase()} · $subtitle',
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(color: SeasColors.ink400, fontSize: 12),
                                        ),
                                      ],
                                    ),
                                  ),
                                  SeasBadge(statusLabel, tone: badgeToneForStatus('${s['status']}')),
                                ],
                              ),
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  if (canQuickApprove)
                                    Expanded(
                                      child: FilledButton(
                                        onPressed: () => _quickApprove(s),
                                        style: FilledButton.styleFrom(
                                          backgroundColor: SeasColors.success,
                                          foregroundColor: Colors.white,
                                          padding: const EdgeInsets.symmetric(vertical: 12),
                                          minimumSize: const Size(0, 40),
                                        ),
                                        child: Text(
                                          type == 'dtr' ? 'Approve DTR' : 'Approve',
                                          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12),
                                        ),
                                      ),
                                    ),
                                  if (canQuickApprove) const SizedBox(width: 8),
                                  Expanded(
                                    child: FilledButton(
                                      onPressed: () => _review(s),
                                      style: FilledButton.styleFrom(
                                        backgroundColor: SeasColors.volt,
                                        foregroundColor: Colors.white,
                                        padding: const EdgeInsets.symmetric(vertical: 12),
                                        minimumSize: const Size(0, 40),
                                      ),
                                      child: Text('Review', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12)),
                                    ),
                                  ),
                                ],
                              ),
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

class _ConsumerTab extends StatefulWidget {
  const _ConsumerTab();
  @override
  State<_ConsumerTab> createState() => _ConsumerTabState();
}

class _ConsumerTabState extends State<_ConsumerTab> {
  List<Map<String, dynamic>> items = [];
  List<Map<String, dynamic>> feederDtrs = [];
  bool loading = true;
  bool loadingFeederDtrs = false;
  /// Active work = ready; pending/completed stay in stored lists (not the open survey list).
  String dtrFilter = 'ready'; // ready | pending | completed | all
  int? zoneId;
  int? substationId;
  int? feederId;
  final TextEditingController _dtrSearch = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
    _dtrSearch.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _dtrSearch.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await api.get('/consumer/approved?per_page=100');
      final raw = (res['data'] as List?) ?? [];
      items = raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      // #region agent log
      _dbgLog('home_shell.dart:_ConsumerTabState._load', 'approved API payload', {
        'total': items.length,
        'totalFromMeta': res['total'],
        'perPage': res['per_page'],
        'rows': items
            .map((s) => {
                  'id': s['id'],
                  'dtr': s['dtr_name'],
                  'code': s['dtr_code'],
                  'feeder_id': s['feeder_id'],
                  'feeder_id_runtimeType': s['feeder_id']?.runtimeType.toString(),
                  'status': s['status'],
                  'consumer_done': s['consumer_survey_completed_at'],
                })
            .toList(),
      }, hypothesisId: 'A', runId: 'post-fix');
      // #endregion
      if (feederId != null) await _loadFeederDtrs(feederId!);
    } catch (e) {
      items = [];
      // #region agent log
      _dbgLog('home_shell.dart:_ConsumerTabState._load', 'approved API failed', {'error': e.toString()}, hypothesisId: 'A', runId: 'post-fix');
      // #endregion
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> _loadFeederDtrs(int id) async {
    setState(() => loadingFeederDtrs = true);
    try {
      final res = await api.get('/consumer/feeder-dtrs?feeder_id=$id');
      final raw = (res['data'] as List?) ?? [];
      feederDtrs = raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      // #region agent log
      _dbgLog('home_shell.dart:_loadFeederDtrs', 'feeder DTR list loaded', {
        'feederId': id,
        'count': feederDtrs.length,
        'names': feederDtrs.map((e) => e['dtr_name']).toList(),
        'statusKeys': feederDtrs.map((e) => e['status_key']).toList(),
        'readyCount': feederDtrs.where((e) => e['can_open'] == true).length,
      }, hypothesisId: 'A', runId: 'post-fix');
      // #endregion
    } catch (e) {
      feederDtrs = [];
      // #region agent log
      _dbgLog('home_shell.dart:_loadFeederDtrs', 'feeder DTR list failed', {'error': e.toString()}, hypothesisId: 'A', runId: 'post-fix');
      // #endregion
    }
    if (mounted) setState(() => loadingFeederDtrs = false);
  }

  int? _asInt(dynamic v) {
    if (v == null) return null;
    if (v is int) return v;
    return int.tryParse('$v');
  }

  String _zoneLabel(Map s) {
    final z = s['zone'];
    if (z is Map) return (z['name'] ?? z['code'] ?? 'Zone').toString();
    return (s['zone_name'] ?? 'Zone').toString();
  }

  String _ssLabel(Map s) {
    final ss = s['substation'];
    if (ss is Map) {
      final name = (ss['name'] ?? '').toString();
      final kv = (ss['capacity_kv'] ?? ss['voltage'] ?? '').toString();
      if (name.isEmpty) return 'Substation';
      return kv.isEmpty ? name : '$name ($kv)';
    }
    return (s['substation_name'] ?? 'Substation').toString();
  }

  String _feederLabel(Map s) {
    final f = s['feeder'];
    if (f is Map) {
      final name = (f['name'] ?? '').toString();
      final code = (f['code'] ?? '').toString();
      if (name.isEmpty) return code.isEmpty ? 'Feeder' : code;
      return code.isEmpty ? name : '$name · $code';
    }
    final name = (s['feeder_name'] ?? '').toString();
    final code = (s['feeder_code'] ?? '').toString();
    if (name.isEmpty) return code.isEmpty ? 'Feeder' : code;
    return code.isEmpty ? name : '$name · $code';
  }

  List<SeasSelectOption> get zoneOptions {
    final map = <int, String>{};
    for (final s in items) {
      final id = _asInt(s['zone_id']);
      if (id == null) continue;
      map.putIfAbsent(id, () => _zoneLabel(s));
    }
    final list = map.entries.map((e) => SeasSelectOption(value: e.key, label: e.value)).toList()
      ..sort((a, b) => a.label.compareTo(b.label));
    return list;
  }

  List<SeasSelectOption> get substationOptions {
    if (zoneId == null) return [];
    final map = <int, String>{};
    for (final s in items) {
      if (_asInt(s['zone_id']) != zoneId) continue;
      final id = _asInt(s['substation_id']);
      if (id == null) continue;
      map.putIfAbsent(id, () => _ssLabel(s));
    }
    final list = map.entries.map((e) => SeasSelectOption(value: e.key, label: e.value)).toList()
      ..sort((a, b) => a.label.compareTo(b.label));
    return list;
  }

  List<SeasSelectOption> get feederOptions {
    if (substationId == null) return [];
    final map = <int, String>{};
    for (final s in items) {
      if (_asInt(s['substation_id']) != substationId) continue;
      final id = _asInt(s['feeder_id']);
      if (id == null) continue;
      map.putIfAbsent(id, () => _feederLabel(s));
    }
    final list = map.entries.map((e) => SeasSelectOption(value: e.key, label: e.value)).toList()
      ..sort((a, b) => a.label.compareTo(b.label));
    return list;
  }

  List<Map<String, dynamic>> get filteredDtrs {
    if (feederId == null) return [];
    final q = _dtrSearch.text.trim().toLowerCase();
    return feederDtrs.where((s) {
      final key = '${s['status_key'] ?? ''}';
      final matchesFilter = switch (dtrFilter) {
        'ready' => s['can_open'] == true || key == 'ready',
        'pending' => key == 'pending' || key == 'pending_approval' || key == 'draft',
        'completed' => key == 'completed',
        _ => true,
      };
      if (!matchesFilter) return false;
      if (q.isEmpty) return true;
      final name = '${s['dtr_name'] ?? ''}'.toLowerCase();
      final code = '${s['dtr_code'] ?? ''}'.toLowerCase();
      return name.contains(q) || code.contains(q);
    }).toList();
  }

  String _statusLabel(String? key) {
    switch (key) {
      case 'ready':
        return 'Ready for consumer survey';
      case 'completed':
        return 'Consumer survey completed';
      case 'pending':
      case 'pending_approval':
        return 'DTR survey submitted — ready for consumer';
      case 'draft':
        return 'DTR survey draft (submit to unlock consumer)';
      case 'rejected':
        return 'DTR survey rejected';
      case 'no_survey':
        return 'No DTR survey yet';
      default:
        return key == null || key.isEmpty ? 'Unavailable' : key;
    }
  }

  Future<void> _requestReactivation(Map<String, dynamic> row) async {
    final surveyId = row['survey_id'];
    if (surveyId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Survey id missing for this DTR')),
      );
      return;
    }
    final reasonCtrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
          'Request re-activate',
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'DTR finished hai. Manager/Admin approve karega, phir aap aur consumers survey kar sakte ho.\n\n'
              'This DTR is finished. Request approval to survey more consumers.',
              style: GoogleFonts.plusJakartaSans(fontSize: 13, height: 1.4, color: SeasColors.ink400),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: reasonCtrl,
              maxLines: 3,
              maxLength: 500,
              decoration: const InputDecoration(
                labelText: 'Reason / कारण (optional)',
                hintText: 'e.g. More consumers found on this DTR',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: SeasColors.volt),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Submit request'),
          ),
        ],
      ),
    );
    final reason = reasonCtrl.text.trim();
    reasonCtrl.dispose();
    if (ok != true || !mounted) return;

    try {
      await api.post('/consumer/$surveyId/reactivate-request', {
        if (reason.isNotEmpty) 'reason': reason,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Request sent — Manager/Admin approval pending / अनुरोध भेजा गया'),
          backgroundColor: SeasColors.ink950,
        ),
      );
      if (feederId != null) await _loadFeederDtrs(feederId!);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(e.toString().replaceFirst('Exception: ', '')),
        backgroundColor: SeasColors.voltDeep,
      ));
    }
  }

  Future<void> _openDtr(Map<String, dynamic> row) async {
    final canOpen = row['can_open'] == true;
    final surveyRaw = row['survey'];
    final statusKey = row['status_key']?.toString();
    final reactivationPending = row['reactivation_pending'] == true;
    final canRequest = row['can_request_reactivation'] == true ||
        (statusKey == 'completed' && !reactivationPending && row['survey_id'] != null);

    if (canOpen && surveyRaw is Map) {
      final survey = Map<String, dynamic>.from(surveyRaw);
      await Navigator.push(
        context,
        MaterialPageRoute(
          settings: const RouteSettings(name: 'pole_selection'),
          builder: (_) => PoleSelectionScreen(dtrSurvey: survey),
        ),
      );
      await _load();
      return;
    }

    if (statusKey == 'completed') {
      if (reactivationPending) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Re-activation pending approval / पुनः सक्रिय अनुरोध लंबित है'),
          ),
        );
        return;
      }
      if (canRequest) {
        await _requestReactivation(row);
        return;
      }
    }

    final msg = _statusLabel(statusKey);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Downstream',
      title: 'Consumer Survey',
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : items.isEmpty
              ? const SeasEmptyState(
                  title: 'No surveyed DTRs yet',
                  subtitle:
                      'Submit a DTR Survey first — Consumer Survey unlocks right after submit (no manager approval). Then select Zone → Substation → Feeder → DTR here.',
                  icon: Icons.people_outline,
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                    children: [
                      SeasCard(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Select hierarchy',
                              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 15),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Zone → Substation → Feeder → DTR, then poles & consumers.',
                              style: TextStyle(color: SeasColors.ink400, fontSize: 12, height: 1.35),
                            ),
                            const SizedBox(height: 16),
                            LayoutBuilder(
                              builder: (context, c) {
                                final wide = c.maxWidth >= 560;
                                final fields = [
                                  SeasSelectField(
                                    label: 'Zone / DC *',
                                    hint: 'Select zone',
                                    value: zoneId,
                                    leadingIcon: Icons.location_on_outlined,
                                    options: zoneOptions,
                                    onSelected: (o) => setState(() {
                                      zoneId = o.value as int;
                                      substationId = null;
                                      feederId = null;
                                      feederDtrs = [];
                                      _dtrSearch.clear();
                                    }),
                                  ),
                                  SeasSelectField(
                                    label: 'Substation *',
                                    hint: zoneId == null ? 'Select zone first' : 'Select substation',
                                    value: substationId,
                                    enabled: zoneId != null,
                                    leadingIcon: Icons.apartment_rounded,
                                    options: substationOptions,
                                    onSelected: (o) => setState(() {
                                      substationId = o.value as int;
                                      feederId = null;
                                      feederDtrs = [];
                                      _dtrSearch.clear();
                                    }),
                                  ),
                                  SeasSelectField(
                                    label: 'Feeder *',
                                    hint: substationId == null ? 'Select substation first' : 'Select feeder',
                                    value: feederId,
                                    enabled: substationId != null,
                                    leadingIcon: Icons.electrical_services_rounded,
                                    options: feederOptions,
                                    onSelected: (o) {
                                      final id = o.value as int;
                                      setState(() {
                                        feederId = id;
                                        _dtrSearch.clear();
                                      });
                                      _loadFeederDtrs(id);
                                    },
                                  ),
                                  _DtrSearchField(
                                    controller: _dtrSearch,
                                    enabled: feederId != null,
                                  ),
                                ];
                                if (!wide) {
                                  return Column(
                                    children: [
                                      for (var i = 0; i < fields.length; i++) ...[
                                        if (i > 0) const SizedBox(height: 14),
                                        fields[i],
                                      ],
                                    ],
                                  );
                                }
                                return Column(
                                  children: [
                                    Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Expanded(child: fields[0]),
                                        const SizedBox(width: 12),
                                        Expanded(child: fields[1]),
                                      ],
                                    ),
                                    const SizedBox(height: 14),
                                    Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Expanded(child: fields[2]),
                                        const SizedBox(width: 12),
                                        Expanded(child: fields[3]),
                                      ],
                                    ),
                                  ],
                                );
                              },
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                      if (feederId == null)
                        SeasCard(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 22),
                          child: Row(
                            children: [
                              SeasIconTile(icon: Icons.route_rounded, bg: SeasColors.voltSoft, fg: SeasColors.volt),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  'Complete Zone → Substation → Feeder to list DTRs.',
                                  style: GoogleFonts.plusJakartaSans(
                                    fontWeight: FontWeight.w600,
                                    color: SeasColors.ink400,
                                    fontSize: 13,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        )
                      else if (loadingFeederDtrs)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 28),
                          child: Center(child: CircularProgressIndicator(color: SeasColors.volt)),
                        )
                      else if (filteredDtrs.isEmpty)
                        SeasEmptyState(
                          title: dtrFilter == 'ready' ? 'No open DTRs' : 'No DTRs match',
                          subtitle: dtrFilter == 'ready'
                              ? 'Completed / pending DTRs are under Pending & Completed chips — not in the active list.'
                              : 'Try another feeder, chip filter, or clear the DTR search.',
                          icon: Icons.search_off_rounded,
                        )
                      else ...[
                        Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          children: [
                            for (final entry in [
                              ('ready', 'Active'),
                              ('pending', 'Pending'),
                              ('completed', 'Completed'),
                              ('all', 'All'),
                            ])
                              ChoiceChip(
                                label: Text(entry.$2),
                                selected: dtrFilter == entry.$1,
                                onSelected: (_) => setState(() => dtrFilter = entry.$1),
                              ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Text(
                          '${filteredDtrs.length} DTR${filteredDtrs.length == 1 ? '' : 's'} · ${dtrFilter == 'ready' ? 'ready for survey' : dtrFilter}',
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                            color: SeasColors.ink400,
                          ),
                        ),
                        const SizedBox(height: 10),
                        ...filteredDtrs.map((s) {
                          final name = '${s['dtr_name'] ?? 'DTR'}';
                          final code = '${s['dtr_code'] ?? ''}'.trim();
                          final canOpen = s['can_open'] == true;
                          final statusKey = s['status_key']?.toString();
                          final reactivationPending = s['reactivation_pending'] == true;
                          final canRequest = s['can_request_reactivation'] == true ||
                              (statusKey == 'completed' && !reactivationPending && s['survey_id'] != null);
                          var status = _statusLabel(statusKey);
                          if (reactivationPending) {
                            status = 'Re-activation pending / अनुरोध लंबित';
                          } else if (canRequest && statusKey == 'completed') {
                            status = 'Finished · tap to request re-activate';
                          }
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: SeasCard(
                              onTap: () => _openDtr(s),
                              padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                              child: Row(
                                children: [
                                  SeasIconTile(
                                    icon: reactivationPending
                                        ? Icons.hourglass_top_rounded
                                        : (canRequest ? Icons.lock_open_rounded : Icons.account_tree_rounded),
                                    bg: canOpen
                                        ? SeasColors.volt
                                        : (canRequest || reactivationPending ? SeasColors.voltSoft : SeasColors.ink200),
                                    fg: canOpen
                                        ? Colors.white
                                        : (canRequest || reactivationPending ? SeasColors.volt : Colors.white),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(name, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800)),
                                        const SizedBox(height: 2),
                                        Text(
                                          code.isEmpty ? status : '$code · $status',
                                          style: TextStyle(
                                            color: canOpen ? SeasColors.ink400 : SeasColors.ink400,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Icon(
                                    canOpen
                                        ? Icons.chevron_right_rounded
                                        : (canRequest
                                            ? Icons.refresh_rounded
                                            : (reactivationPending
                                                ? Icons.schedule_rounded
                                                : Icons.lock_outline_rounded)),
                                    color: canOpen || canRequest ? SeasColors.volt : SeasColors.ink200,
                                  ),
                                ],
                              ),
                            ),
                          );
                        }),
                      ],
                    ],
                  ),
                ),
    );
  }
}

class _DtrSearchField extends StatelessWidget {
  const _DtrSearchField({required this.controller, required this.enabled});
  final TextEditingController controller;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'DTR Search',
          style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: SeasColors.ink400),
        ),
        const SizedBox(height: 8),
        AnimatedOpacity(
          duration: const Duration(milliseconds: 180),
          opacity: enabled ? 1 : 0.55,
          child: TextField(
            controller: controller,
            enabled: enabled,
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w600, fontSize: 14),
            decoration: InputDecoration(
              hintText: enabled ? 'Search by DTR Code / Name' : 'Select feeder first',
              hintStyle: const TextStyle(color: SeasColors.ink400, fontWeight: FontWeight.w500, fontSize: 13),
              prefixIcon: const Icon(Icons.search_rounded, color: SeasColors.ink400),
              filled: true,
              fillColor: enabled ? SeasColors.white : SeasColors.canvasSoft,
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: const BorderSide(color: SeasColors.ink200),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: const BorderSide(color: SeasColors.ink200),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: const BorderSide(color: SeasColors.volt, width: 1.4),
              ),
              disabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: const BorderSide(color: SeasColors.ink200),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _ProfileTab extends StatelessWidget {
  const _ProfileTab({
    required this.user,
    required this.isManager,
    required this.onLogout,
    required this.onJump,
    required this.onUserChanged,
  });
  final Map<String, dynamic>? user;
  final bool isManager;
  final Future<void> Function() onLogout;
  final ValueChanged<int> onJump;
  final ProfileUserChanged onUserChanged;

  @override
  Widget build(BuildContext context) {
    return SeasPageScaffold(
      eyebrow: 'Account',
      title: 'Profile',
      child: ProfileFeatureList(
        user: user,
        isManager: isManager,
        showHeader: true,
        onLogout: onLogout,
        onJump: onJump,
        onUserChanged: onUserChanged,
      ),
    );
  }
}
