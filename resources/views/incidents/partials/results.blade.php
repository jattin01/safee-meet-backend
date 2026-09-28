<div style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
    <table style="width:100%; min-width:500px; border-collapse:collapse;">
        <tbody>
            @forelse($incidents as $incident)
                <tr style="border-bottom:1px solid #1a1a1a;">
                    <td style="padding:16px 20px; width:16px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:{{ $incident->status_color }};"></span>
                    </td>
                    <td style="padding:16px 10px;">
                        <div style="font-size:13px; font-weight:600; color:#fff;">{{ $incident->reporter->display_name ?? $incident->reporter->name ?? 'Anonymous' }}</div>
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $incident->type_label }} &middot; {{ $incident->meeting->location ?? 'Location unavailable' }}</div>
                    </td>
                    <td style="padding:16px 10px; text-align:right; white-space:nowrap;">
                        <span style="font-size:11px; color:#6b7280; margin-right:12px;">{{ $incident->created_at->diffForHumans() }}</span>
                        <span style="background:{{ $incident->status_color }}26; color:{{ $incident->status_color }}; font-size:11px; padding:4px 12px; border-radius:999px;">{{ $incident->status_label }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="padding:32px 20px; text-align:center; color:#6b7280; font-size:13px;">No incidents match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($incidents->hasPages())
    <div style="padding:16px 20px; border-top:1px solid #1a1a1a;">
        {{ $incidents->links() }}
    </div>
@endif
