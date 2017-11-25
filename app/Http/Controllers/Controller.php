<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Controller extends BaseController
{
    //
    public function rss(Request $request)
    {
        if (!$request->has('key')) {
            return abort(400, "Please provide a TMDB API key in the 'key' query param");
        }

        if (!$request->has('id')) {
            return abort(400, "Please provide one or more comma seperated TMDB id's for people whose movies should be returned");
        }

        $key = $request->key;
        $id = $request->id;

        // https://developers.themoviedb.org/3/getting-started/request-rate-limiting
        // Lets use just below capacity to be nice
        $ratelimit = $this->ratelimiter(30, 10);

        $res = collect(explode(',', $id))
            // Map actors to their movie credits
            ->map(function ($id) use ($key, &$ratelimit) {
                $url = "https://api.themoviedb.org/3/person/{$id}/movie_credits?api_key={$key}&language=en-US";
                return Cache::remember($url, 600, function () use ($url, &$ratelimit) {
                    $client = new \GuzzleHttp\Client();
                    // Ensure we dont hit TMDB cap
                    $ratelimit();
                    return $client->request('GET', $url, [
                        'headers' => [
                            'Accept' => 'application/json'
                        ]
                    ])->getBody()->getContents();
                });
            })
            ->map(function ($response) {
                return json_decode($response);
            })
            // Map their cast credits to movie info
            ->map(function ($obj) {
                return collect($obj->cast)->map(function ($el) {
                    return (object)[
                        'title' => $el->title,
                        'year' => isset($el->release_date) ? explode('-', $el->release_date)[0] : null,
                        'id' => $el->id
                    ];
                });
            })
            // Join all actors to a single flat array
            ->flatten()
            ->unique()
            // Fetch movie details
            ->map(function ($movie) use ($key, $ratelimit) {
                $url = "https://api.themoviedb.org/3/movie/{$movie->id}?api_key={$key}&language=en-US";
                return Cache::remember($url, 600, function () use ($url, &$ratelimit) {
                    $client = new \GuzzleHttp\Client();
                    // Ensure we dont hit TMDB cap
                    $ratelimit();
                    return $client->request('GET', $url, [
                        'headers' => [
                            'Accept' => 'application/json'
                        ]
                    ])->getBody()->getContents();
                });
            })
            ->map(function ($response) {
                return json_decode($response);
            })
            // Map movie responses to simple objects
            ->map(function ($movie) {
                return [
                    'title' => $movie->title,
                    'year' => isset($movie->release_date) ? explode('-', $movie->release_date)[0] : null,
                    'release_date' => isset($movie->release_date) ? $movie->release_date : null,
                    'id' => $movie->id,
                    'imdb' => $movie->imdb_id
                ];
            })
            ->values();

        return response(view('rss', ['movies' => $res]))
                ->header('Content-Type', 'text/xml');
    }

    // http://stackoverflow.com/a/29528426
    protected function ratelimiter($rate = 5, $per = 8)
    {
        $last_check = microtime(true);
        $allowance = $rate;

        return function ($consumed = 1) use (
            &$last_check,
            &$allowance,
            $rate,
            $per
        ) {
            $current = microtime(true);
            $time_passed = $current - $last_check;
            $last_check = $current;

            $allowance += $time_passed * ($rate / $per);
            if ($allowance > $rate) {
                $allowance = $rate;
            }

            if ($allowance < $consumed) {
                $duration = ($consumed - $allowance) * ($per / $rate);
                $last_check += $duration;
                usleep($duration * 1000000);
                $allowance = 0;
            } else {
                $allowance -= $consumed;
            }
            return;
        };
    }
}
