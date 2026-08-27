<?php

namespace App\Http\Controllers;

use App\Models\MediaPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MediaPostController extends Controller
{
    public function metadata(Request $request)
    {
        $url = $request->validate(['url' => ['required', 'url:http,https', 'max:2000']])['url'];
        $media = $this->parseUrl($url);
        return ['title' => $this->resolveTitle($url, $media)];
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['video', 'image'])],
            'sort' => ['nullable', Rule::in(['new', 'popular'])],
            'favorites' => ['nullable', 'boolean'],
        ]);
        $userId = $request->user()->id;

        return MediaPost::query()
            ->with(['author:id,discord_username,discord_display_name', 'author.player:id,user_id,nickname'])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('type', 'like'),
                'reactions as favorites_count' => fn ($q) => $q->where('type', 'favorite'),
            ])
            ->withExists([
                'reactions as liked_by_me' => fn ($q) => $q->where('user_id', $userId)->where('type', 'like'),
                'reactions as favorite_by_me' => fn ($q) => $q->where('user_id', $userId)->where('type', 'favorite'),
            ])
            ->when($data['search'] ?? null, fn ($q, $value) => $q->where(fn ($post) => $post
                ->where('title', 'ilike', "%{$value}%")
                ->orWhere('description', 'ilike', "%{$value}%")))
            ->when($data['kind'] ?? null, fn ($q, $value) => $q->where('kind', $value))
            ->when($data['favorites'] ?? false, fn ($q) => $q->whereHas('reactions', fn ($r) => $r->where('user_id', $userId)->where('type', 'favorite')))
            ->when(($data['sort'] ?? 'new') === 'popular',
                fn ($q) => $q->orderByRaw("(select count(*) from media_reactions where media_reactions.media_post_id = media_posts.id and type = 'like') desc")->latest('id'),
                fn ($q) => $q->latest())
            ->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url:http,https', 'max:2000', 'required_without:file'],
            'file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:20480', 'required_without:url'],
        ]);

        $attributes = ['user_id' => $request->user()->id, 'description' => $data['description'] ?? null];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attributes += [
                'title' => ($data['title'] ?? null) ?: (pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Изображение'),
                'kind' => 'image',
                'provider' => 'upload',
                'file_path' => $file->store('media', 'local'),
            ];
        } else {
            $media = $this->parseUrl($data['url']);
            $attributes += $media + ['title' => ($data['title'] ?? null) ?: $this->resolveTitle($data['url'], $media)];
        }

        return response()->json(MediaPost::query()->create($attributes), 201);
    }

    public function media(Request $request, MediaPost $mediaPost)
    {
        abort_unless($mediaPost->file_path && Storage::disk('local')->exists($mediaPost->file_path), 404);
        $response = Storage::disk('local')->response($mediaPost->file_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'Accept-Ranges' => 'bytes',
        ]);
        // In production Nginx serves the bytes after Laravel has checked access,
        // so a long video does not occupy a PHP-FPM worker.
        if (app()->isProduction()) $response->headers->set('X-Accel-Redirect', '/protected-media/'.basename($mediaPost->file_path));
        return $response;
    }

    public function react(Request $request, MediaPost $mediaPost)
    {
        $type = $request->validate(['type' => ['required', Rule::in(['like', 'favorite'])]])['type'];
        $reaction = $mediaPost->reactions()->where('user_id', $request->user()->id)->where('type', $type)->first();
        $reaction ? $reaction->delete() : $mediaPost->reactions()->create(['user_id' => $request->user()->id, 'type' => $type]);
        return response()->json(['active' => !$reaction]);
    }

    public function destroy(Request $request, MediaPost $mediaPost)
    {
        abort_unless($mediaPost->user_id === $request->user()->id || $request->user()->canAdministrate(), 403);
        if ($mediaPost->file_path) Storage::disk('local')->delete($mediaPost->file_path);
        $mediaPost->delete();
        return response()->noContent();
    }

    private function parseUrl(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            $id = $host === 'youtu.be' ? trim($path, '/') : ($query['v'] ?? (preg_match('~/shorts/([\w-]+)~', $path, $m) ? $m[1] : null));
            if ($id && preg_match('/^[\w-]{6,20}$/', $id)) return ['kind'=>'video','provider'=>'youtube','source_url'=>$url,'embed_url'=>"https://www.youtube-nocookie.com/embed/{$id}",'thumbnail_url'=>"https://i.ytimg.com/vi/{$id}/hqdefault.jpg"];
        }
        if (str_ends_with($host, 'rutube.ru') && preg_match('~/video/([a-zA-Z0-9]+)~', $path, $m))
            return ['kind'=>'video','provider'=>'rutube','source_url'=>$url,'embed_url'=>"https://rutube.ru/play/embed/{$m[1]}"];
        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('~/(\d+)~', $path, $m))
            return ['kind'=>'video','provider'=>'vimeo','source_url'=>$url,'embed_url'=>"https://player.vimeo.com/video/{$m[1]}"];
        if (preg_match('/\.(mp4|webm|mov)(?:\?|$)/i', $url))
            return ['kind'=>'video','provider'=>'direct','source_url'=>$url,'embed_url'=>null];
        if (preg_match('/\.(jpe?g|png|gif|webp)(?:\?|$)/i', $url))
            return ['kind'=>'image','provider'=>'direct','source_url'=>$url,'embed_url'=>null,'thumbnail_url'=>$url];

        throw ValidationException::withMessages(['url' => __('domain.media.unsupported_url')]);
    }

    private function resolveTitle(string $url, array $media): string
    {
        $rutubeId = $media['provider'] === 'rutube' && preg_match('~/embed/([a-zA-Z0-9]+)~', $media['embed_url'], $match) ? $match[1] : null;
        $endpoint = match ($media['provider']) {
            'youtube' => 'https://www.youtube.com/oembed',
            'rutube' => "https://rutube.ru/api/video/{$rutubeId}/",
            'vimeo' => 'https://vimeo.com/api/oembed.json',
            default => null,
        };

        if ($endpoint) {
            try {
                $query = $media['provider'] === 'rutube' ? [] : ['url' => $url, 'format' => 'json'];
                $response = Http::acceptJson()->timeout(4)->get($endpoint, $query);
                $title = trim((string) $response->json('title'));
                if ($response->successful() && $title !== '') return mb_substr($title, 0, 160);
            } catch (\Throwable) {
                // A platform outage must not block publishing; use a safe fallback.
            }
        }

        $filename = rawurldecode(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME));
        if ($filename !== '') return mb_substr(str_replace(['-', '_'], ' ', $filename), 0, 160);
        return match ($media['provider']) {
            'youtube' => 'Видео YouTube', 'rutube' => 'Видео Rutube', 'vimeo' => 'Видео Vimeo',
            default => 'Новая публикация',
        };
    }
}
