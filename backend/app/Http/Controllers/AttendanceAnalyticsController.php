<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\GuildGroup;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttendanceAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($this->build($request));
    }

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])]])['format'];
        $report = $this->build($request);
        $filename = 'attendance-'.$report['period']['key'].'-'.now()->format('Y-m-d');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($report): void {
                $output = fopen('php://output', 'wb');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Игрок','Конста','Посещено','Доступно','Посещаемость, %','Серия посещений','Серия пропусков','Последнее посещение'], ';');
                foreach ($report['players'] as $player) fputcsv($output, [$player['nickname'],$player['group_name'],$player['attended'],$player['available'],$player['percentage'],$player['attendance_streak'],$player['absence_streak'],$player['last_attended_at']], ';');
                fclose($output);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Посещаемость');
        $sheet->fromArray(['Игрок','Конста','Посещено','Доступно','Посещаемость, %','Серия посещений','Серия пропусков','Последнее посещение'], null, 'A1');
        $row = 2;
        foreach ($report['players'] as $player) {
            $sheet->fromArray([$player['nickname'],$player['group_name'],$player['attended'],$player['available'],$player['percentage'],$player['attendance_streak'],$player['absence_streak'],$player['last_attended_at']], null, 'A'.$row++);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A', 'H') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $events = $spreadsheet->createSheet()->setTitle('События');
        $events->fromArray(['Событие','Проведено','Посещений','Среднее участников'], null, 'A1');
        $row = 2;
        foreach ($report['events'] as $event) $events->fromArray([$event['name'],$event['total'],$event['attendances'],$event['average_participants']], null, 'A'.$row++);
        $events->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (range('A', 'D') as $column) $events->getColumnDimension($column)->setAutoSize(true);
        $groups = $spreadsheet->createSheet()->setTitle('Консты');
        $groups->fromArray(['Конста','Игроков','Посещено','Доступно','Посещаемость, %'], null, 'A1');
        $row = 2;
        foreach ($report['groups'] as $group) $groups->fromArray([$group['name'],$group['players'],$group['attended'],$group['available'],$group['percentage']], null, 'A'.$row++);
        $groups->getStyle('A1:E1')->getFont()->setBold(true);
        foreach (range('A', 'E') as $column) $groups->getColumnDimension($column)->setAutoSize(true);

        $path = tempnam(sys_get_temp_dir(), 'attendance-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return response()->download($path, $filename.'.xlsx')->deleteFileAfterSend(true);
    }

    private function build(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->canManageGuild() || $user->hasRole(UserRole::PartyLeader), 403);
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['7','30','90','all'])],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'definition_id' => ['nullable', 'integer', 'exists:activity_definitions,id'],
            'player_id' => ['nullable', 'integer', 'exists:players,id'],
            'inactive_days' => ['nullable', 'integer', 'min:7', 'max:3650'],
            'format' => ['nullable', Rule::in(['csv','xlsx'])],
        ]);
        $period = $filters['period'] ?? '30';
        $from = $period === 'all' ? null : now()->subDays((int) $period)->startOfDay();
        $partyGroupId = $user->hasRole(UserRole::PartyLeader) && !$user->canManageGuild() ? $user->player?->group_id : null;
        if ($user->hasRole(UserRole::PartyLeader) && !$user->canManageGuild()) abort_unless($partyGroupId, 403, 'PL не привязан к конст-пати.');
        $groupId = $partyGroupId ?: ($filters['group_id'] ?? null);

        $players = Player::query()->where('is_active', true)
            ->when($groupId, fn ($query) => $query->where('group_id', $groupId))
            ->with(['group:id,name','user:id,discord_id,discord_username,discord_display_name,discord_avatar'])
            ->orderBy('nickname')->get();
        $activities = Activity::query()
            ->countedInStatistics()
            ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
            ->when($from, fn ($query) => $query->where('occurred_at', '>=', $from))
            ->when($filters['definition_id'] ?? null, fn ($query, $id) => $query->where('activity_definition_id', $id))
            ->with(['definition:id,name,type,icon_path','players:id'])
            ->orderBy('occurred_at')->get();

        $rows = $players->map(fn (Player $player) => $this->playerRow($player, $activities));
        $selectedId = (int) ($filters['player_id'] ?? $players->first()?->id ?? 0);
        if ($partyGroupId && !$players->contains('id', $selectedId)) $selectedId = (int) ($players->first()?->id ?? 0);
        $selected = $players->firstWhere('id', $selectedId);

        $events = $activities->groupBy('activity_definition_id')->map(function (Collection $items): array {
            $definition = $items->first()->definition;
            $attendances = $items->sum(fn ($activity) => $activity->players->count());
            return ['id'=>$definition->id,'name'=>$definition->name,'icon_url'=>$definition->icon_url,'total'=>$items->count(),'attendances'=>$attendances,'average_participants'=>round($attendances / max(1, $items->count()), 1)];
        })->sortBy('name', SORT_NATURAL|SORT_FLAG_CASE)->values();

        $groups = $rows->groupBy('group_name')->map(function (Collection $items, string $name): array {
            $available = $items->sum('available'); $attended = $items->sum('attended');
            return ['name'=>$name,'players'=>$items->count(),'attended'=>$attended,'available'=>$available,'percentage'=>$available ? round($attended / $available * 100, 1) : 0];
        })->sortByDesc('percentage')->values();
        $inactiveDays = (int) ($filters['inactive_days'] ?? 30);
        $inactiveBefore = now()->subDays($inactiveDays);
        $inactive = $rows->filter(fn ($row) => !$row['last_attended_at'] || Carbon::parse($row['last_attended_at'])->lt($inactiveBefore))->values();

        return [
            'period' => ['key'=>$period,'from'=>$from?->toISOString(),'to'=>now()->toISOString(),'total_primes'=>$activities->count()],
            'summary' => ['players'=>$rows->count(),'attended'=>$rows->sum('attended'),'available'=>$rows->sum('available'),'percentage'=>$rows->sum('available') ? round($rows->sum('attended')/$rows->sum('available')*100,1) : 0],
            'players' => $rows->sortByDesc('percentage')->values(),
            'events' => $events,
            'groups' => $groups,
            'inactive' => $inactive,
            'inactive_days' => $inactiveDays,
            'timeline' => $selected ? $this->timeline($selected, $activities, $period) : [],
            'selected_player_id' => $selected?->id,
            'options' => [
                'groups' => GuildGroup::query()->when($partyGroupId, fn ($query) => $query->whereKey($partyGroupId))->orderBy('name')->get(['id','name']),
                'definitions' => ActivityDefinition::query()->where('type','prime')->orderBy('name')->get(['id','name']),
                'players' => $players->map->only(['id','nickname'])->values(),
            ],
        ];
    }

    private function playerRow(Player $player, Collection $activities): array
    {
        $available = $activities->filter(fn ($activity) => $activity->occurred_at->gte($player->created_at));
        $sequence = $available->map(fn ($activity) => $activity->players->contains('id', $player->id))->values();
        $attended = $sequence->filter()->count();
        $last = $available->filter(fn ($activity) => $activity->players->contains('id', $player->id))->last();
        [$attendanceStreak, $absenceStreak] = $this->currentStreaks($sequence);
        return array_merge($player->only(['id','nickname','class']), [
            'group_id'=>$player->group_id,'group_name'=>$player->group?->name ?? 'Без консты','attended'=>$attended,'available'=>$available->count(),
            'percentage'=>$available->count() ? round($attended/$available->count()*100,1) : 0,
            'attendance_streak'=>$attendanceStreak,'absence_streak'=>$absenceStreak,'last_attended_at'=>$last?->occurred_at?->toISOString(),
            'user'=>$player->user,
        ]);
    }

    private function currentStreaks(Collection $sequence): array
    {
        $attendance = 0; $absence = 0;
        foreach ($sequence->reverse() as $visited) {
            if ($visited && $absence === 0) $attendance++;
            elseif (!$visited && $attendance === 0) $absence++;
            else break;
        }
        return [$attendance, $absence];
    }

    private function timeline(Player $player, Collection $activities, string $period): array
    {
        $format = $period === '7' ? 'Y-m-d' : 'o-W';
        return $activities->filter(fn ($activity) => $activity->occurred_at->gte($player->created_at))->groupBy(fn ($activity) => $activity->occurred_at->format($format))->map(function (Collection $items, string $label) use ($player): array {
            $attended = $items->filter(fn ($activity) => $activity->players->contains('id',$player->id))->count();
            return ['label'=>$label,'attended'=>$attended,'available'=>$items->count(),'percentage'=>round($attended/$items->count()*100,1)];
        })->values()->all();
    }
}
