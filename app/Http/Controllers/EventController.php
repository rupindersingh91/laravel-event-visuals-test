<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\CityAnchors;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
                'to' => $request->input('to'),
                'city' => $request->input('city'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
            'cities' => CityAnchors::labels(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $items, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => $items,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load('user');

        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }

    public function visualOne(): Response
    {
        return Inertia::render('Events/VisualOne', [
            'cities' => CityAnchors::labels(),
        ]);
    }

    public function visualTwo(): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'cities' => CityAnchors::labels(),
            'cityCoords' => CityAnchors::coordsByLabel(),
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: list<array<string, mixed>>, 2: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $events = Event::with('user')
            ->when($request->status, function ($q, $s) {
                return $q->where('status', $s);
            })
            ->when($request->from, function ($q, $from) {
                return $q->where('created_time', '>=', Carbon::parse($from, 'UTC')->startOfDay()->timestamp);
            })
            ->when($request->to, function ($q, $to) {
                return $q->where('created_time', '<=', Carbon::parse($to, 'UTC')->endOfDay()->timestamp);
            })
            ->when($request->city, function ($q, $city) {
                $box = CityAnchors::boundingBox($city);
                if ($box) {
                    $q->whereBetween('latitude', [$box['lat_min'], $box['lat_max']])
                        ->whereBetween('longitude', [$box['lng_min'], $box['lng_max']]);
                }
            })
            ->orderByDesc('created_time')
            ->paginate(50)
            ->withQueryString();

        $items = $events->getCollection()->map(function (Event $event) {
            return $this->formatRow($event);
        })->all();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($items)),
        ];

        return [$events, $items, $stats];
    }

    /** @return array<string, mixed> */
    private function formatRow(Event $event): array
    {
        $payload = $event->payload ?? [];
        $pricing = $payload['pricing']['min_price'] ?? null;

        return [
            'id' => $event->id,
            'name' => (string) ($payload['name'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'type' => $event->type,
            'status' => $event->status,
            'created_time' => $event->created_time,
            'starts_at_iso' => $event->starts_at_iso,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'location_name' => $event->location_name,
            'image_urls' => $event->image_urls,
            'min_price' => is_numeric($pricing) ? (float) $pricing : null,
            'venue' => (string) ($payload['venue']['name'] ?? ''),
            'user' => $event->user ? ['id' => $event->user->id, 'name' => $event->user->name] : null,
        ];
    }
}
