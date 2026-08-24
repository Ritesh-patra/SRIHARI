import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/seas_colors.dart';

class SeasSelectOption {
  const SeasSelectOption({required this.value, required this.label, this.subtitle});
  final dynamic value;
  final String label;
  final String? subtitle;
}

/// Premium tappable field that opens a searchable bottom sheet (no ugly dropdown).
class SeasSelectField extends StatelessWidget {
  const SeasSelectField({
    super.key,
    required this.label,
    required this.hint,
    required this.options,
    required this.onSelected,
    this.value,
    this.enabled = true,
    this.icon = Icons.expand_more_rounded,
    this.leadingIcon,
  });

  final String label;
  final String hint;
  final dynamic value;
  final List<SeasSelectOption> options;
  final ValueChanged<SeasSelectOption> onSelected;
  final bool enabled;
  final IconData icon;
  final IconData? leadingIcon;

  String get _display {
    if (value == null) return '';
    for (final o in options) {
      if (o.value == value) return o.label;
    }
    return '$value';
  }

  Future<void> _open(BuildContext context) async {
    if (!enabled) return;
    final selected = await showModalBottomSheet<SeasSelectOption>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _SeasSelectSheet(title: label, options: options, selected: value),
    );
    if (selected != null) onSelected(selected);
  }

  @override
  Widget build(BuildContext context) {
    final has = _display.isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
        const SizedBox(height: 8),
        Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: enabled ? () => _open(context) : null,
            borderRadius: BorderRadius.circular(16),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
              decoration: BoxDecoration(
                color: enabled ? SeasColors.white : SeasColors.canvasSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: has ? SeasColors.ink950.withValues(alpha: 0.12) : SeasColors.ink200),
                boxShadow: enabled
                    ? const [BoxShadow(color: Color(0x0A0F172A), blurRadius: 10, offset: Offset(0, 4))]
                    : null,
              ),
              child: Row(
                children: [
                  if (leadingIcon != null) ...[
                    Container(
                      height: 34,
                      width: 34,
                      decoration: BoxDecoration(
                        color: SeasColors.canvas,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(leadingIcon, size: 18, color: SeasColors.ink700),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    child: Text(
                      has ? _display : hint,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: has ? FontWeight.w700 : FontWeight.w600,
                        fontSize: 14,
                        color: has ? SeasColors.ink950 : SeasColors.ink400,
                      ),
                    ),
                  ),
                  Icon(icon, color: enabled ? SeasColors.volt : SeasColors.ink200),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _SeasSelectSheet extends StatefulWidget {
  const _SeasSelectSheet({required this.title, required this.options, this.selected});
  final String title;
  final List<SeasSelectOption> options;
  final dynamic selected;

  @override
  State<_SeasSelectSheet> createState() => _SeasSelectSheetState();
}

class _SeasSelectSheetState extends State<_SeasSelectSheet> {
  final query = TextEditingController();

  @override
  void dispose() {
    query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final q = query.text.trim().toLowerCase();
    final filtered = widget.options.where((o) {
      if (q.isEmpty) return true;
      return o.label.toLowerCase().contains(q) || (o.subtitle?.toLowerCase().contains(q) ?? false);
    }).toList();

    final h = MediaQuery.sizeOf(context).height * 0.72;

    return Container(
      height: h,
      decoration: const BoxDecoration(
        color: SeasColors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(width: 42, height: 4, decoration: BoxDecoration(color: SeasColors.ink200, borderRadius: BorderRadius.circular(99))),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 12, 8),
            child: Row(
              children: [
                Expanded(
                  child: Text(widget.title, style: GoogleFonts.plusJakartaSans(fontSize: 20, fontWeight: FontWeight.w800, letterSpacing: -0.4)),
                ),
                IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close_rounded)),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
            child: TextField(
              controller: query,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: 'Search…',
                prefixIcon: const Icon(Icons.search_rounded, color: SeasColors.ink400),
                filled: true,
                fillColor: SeasColors.canvas,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: SeasColors.volt, width: 1.4),
                ),
              ),
            ),
          ),
          Expanded(
            child: filtered.isEmpty
                ? Center(child: Text('No results', style: GoogleFonts.plusJakartaSans(color: SeasColors.ink400, fontWeight: FontWeight.w600)))
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 24),
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 6),
                    itemBuilder: (_, i) {
                      final o = filtered[i];
                      final selected = o.value == widget.selected;
                      return Material(
                        color: selected ? SeasColors.voltSoft : SeasColors.canvasSoft,
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => Navigator.pop(context, o),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(o.label, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, color: selected ? SeasColors.voltDeep : SeasColors.ink950)),
                                      if (o.subtitle != null) ...[
                                        const SizedBox(height: 2),
                                        Text(o.subtitle!, style: const TextStyle(color: SeasColors.ink400, fontSize: 12)),
                                      ],
                                    ],
                                  ),
                                ),
                                if (selected) const Icon(Icons.check_circle_rounded, color: SeasColors.volt),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class SeasTextField extends StatelessWidget {
  const SeasTextField({
    super.key,
    required this.label,
    required this.controller,
    this.hint,
    this.maxLines = 1,
    this.maxLength,
    this.keyboardType,
    this.inputFormatters,
    this.onChanged,
    this.autofocus = false,
  });
  final String label;
  final TextEditingController controller;
  final String? hint;
  final int maxLines;
  final int? maxLength;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final ValueChanged<String>? onChanged;
  final bool autofocus;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w700, color: SeasColors.ink400)),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          maxLines: maxLines,
          maxLength: maxLength,
          keyboardType: keyboardType,
          inputFormatters: inputFormatters,
          onChanged: onChanged,
          autofocus: autofocus,
          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700),
          decoration: InputDecoration(
            hintText: hint,
            counterText: maxLength != null ? '' : null,
            filled: true,
            fillColor: SeasColors.white,
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: SeasColors.ink200)),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: SeasColors.ink200)),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: SeasColors.volt, width: 1.5)),
          ),
        ),
      ],
    );
  }
}
