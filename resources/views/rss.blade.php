<rss version="2.0">
<channel>
	<title>Movies with Actors</title>
	<link>{{ app('url')->current() }}</link>
	<description/>
	<pubDate>{{ date('Y-m-d H:i:s') }}</pubDate>
@foreach ($movies as $movie)
	<item>
			<pubDate>{{ $movie['release_date'] }}</pubDate>
			<title>{{ $movie['title'] }} ({{ $movie['year'] }})</title>
			<link>http://www.imdb.com/title/{{ $movie['imdb'] }}/</link>
			<guid>https://www.themoviedb.org/movie/{{ $movie['id'] }}/</guid>
	</item>
@endforeach
</channel>
</rss>