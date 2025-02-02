<?php

namespace App\Services;

use App\Repositories\MovieRepositoryInterface;
use App\Repositories\OmdbMovieRepository;
use App\Models\Actor;
use App\Models\Comment;
use App\Models\Film;
use App\Models\Genre;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use App\Http\Resources\FilmListResource;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\UpdateFilmRequest;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Прикладной сервис MovieService,
 * используя MovieRepositoryInterface осуществляет все операции с сущностью Movie
 *
 * @property MovieRepositoryInterface $movieRepository
 */
class FilmService
{
    public function __construct(
        private $movieRepository = new OmdbMovieRepository(new Client())
    ) {
    }

    /**
     * Метод поиска фильма по его id в базе данных OMDB (https://www.omdbapi.com/)
     * @param  string $imdbId - id фильма в базе данных OMDB
     *
     * @return array|null - массив с информацией о фильме,
     * полученный из базы данных OMDB через конкретную реализацию интерфейса репозитория MovieRepositoryInterface
     */
    public function searchFilm(string $imdbId): array|null
    {
        return $this->movieRepository->findById($imdbId);
    }

    /**
     * Метод создания модели класса Film
     *
     * @param  array $filmData - массив с данными фильма из базы данных OMDB
     *
     * @return Film - вновьсозданная, несохранённая в БД модель класса Film
     */
    private function createFilm(array $filmData): Film
    {
        return new Film([
            'name' => $filmData['name'],
            'poster_image' => $filmData['poster_image'],
            'description' => $filmData['description'],
            'director' => $filmData['director'],
            'run_time' => $filmData['run_time'],
            'released' => $filmData['released'],
            'imdb_id' => $filmData['imdb_id'],
            'status' => FILM::FILM_STATUS_MAP['pending'],
        ]);
    }

    /**
     * Метод получения id актёров для создания-обновления фильма
     *
     * @param  array $actors - массив с именами актёров, участвоваших в фильме
     *
     * @return array $actorsId - массив с id актёров, участвоваших в фильме
     */
    private function getIdOfFilmActors(array $actors = null): array
    {
        $actorsId = [];

        if (is_iterable($actors)) {
            foreach ($actors as $actor) {
                $actorsId[] = Actor::firstOrCreate(['name' => $actor])->id;
            }
        }
        return $actorsId;
    }

    /**
     * Метод получения id жанров для создания-обновления фильма
     *
     * @param  array $genres - массив с названиями жанров фильма
     *
     * @return array $genresId - массив с id жанров фильма
     */
    private function getIdOfFilmGenres(array $genres = null): array
    {
        $genresId = [];

        if (is_iterable($genres)) {
            foreach ($genres as $genre) {
                $genresId[] = Genre::firstOrCreate(['title' => $genre])->id;
            }
        }
        return $genresId;
    }

    /**
     * Метод сохранения фильма в базе
     *
     * @param  array|null $filmData - массив с данными фильма из базы данных OMDB
     *
     * @return void
     */
    public function saveFilm(?array $filmData): void
    {
        try {
            DB::beginTransaction();

            $actorsId = $this->getIdOfFilmActors($filmData['actors']);
            $genresId = $this->getIdOfFilmGenres($filmData['genres']);

            $film = $this->createFilm($filmData);

            $film->save();

            $film->actors()->attach($actorsId);
            $film->genres()->attach($genresId);

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::warning($exception->getMessage());
        }
    }

    /**
     * Редактирование фильма.
     *
     * @param  UpdateFilmRequest $request
     *
     * @return void
     */
    public function updateFilm(UpdateFilmRequest $request, Film $film): void
    {
        $film->fill([
            'name' => $request->name,
            'poster_image' => $request->poster_image,
            'preview_image' => $request->preview_image,
            'background_image' => $request->background_image,
            'video_link' => $request->video_link,
            'preview_video_link' => $request->preview_video_link,
            'director' => $request->director,
            'background_color' => $request->background_color,
            'description' => $request->description,
            'run_time' => $request->run_time,
            'released' => $request->released,
            'imdb_id' => $request->imdb_id,
            'status' => $request->status,
        ]);

        try {
            DB::beginTransaction();

            $actorsId = $this->getIdOfFilmActors($request->starring);
            $genresId = $this->getIdOfFilmGenres($request->genre);

            if ($film->isDirty()) {
                $film->save();
            }

            $film->actors()->sync($actorsId);
            $film->genres()->sync($genresId);

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::warning($exception->getMessage());
        }
    }

    /**
     * Метод добавления фильма к Promo
     *
     * @param  Request $request
     *
     * @return int Код состояния HTTP
     */
    public static function createPromo(Request $request): int
    {
        try {
            DB::beginTransaction();

            Film::where('promo', true)
                ->update(['promo' => false]);

            $newPromo = Film::findOrFail($request->id);
            $newPromo->promo = true;
            $newPromo->save();

            DB::commit();
            return Response::HTTP_CREATED;
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::warning($exception->getMessage());
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }
    }

    /**
     * Метод определения, находится ли фильм в списках избранного у пользователя
     * @param  Film $film - модель класса Film
     * @param  User $user - пользователь
     *
     * @return bool - массив с информацией о фильме,
     * полученный из базы данных OMDB через конкретную реализацию интерфейса репозитория MovieRepositoryInterface
     */
    public static function isFavorite(Film $film, User $user): bool
    {
        $favoriteUsers = $film->users;
        foreach ($favoriteUsers as $favoriteUser) {
            if ($favoriteUser->id === $user->id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Метод создания запроса в БД на получение списка фильмов согласно парамертам в $request
     * @param  Request $request
     *
     * @return Builder $query - экземпляр построителя запросов в БД, сформированный согласно входным параметрам
     */
    public static function createRequestForFilmsByParameters(Request $request): Builder
    {
        $orderTo = 'desc';
        $orderBy = 'released';
        $status  = 'ready';

        if (isset(Auth::user()->is_moderator) && Auth::user()->is_moderator && $request->status) {
            $status = $request->status;
        }

        if (isset($request->order_to)) {
            $orderTo = $request->order_to;
        }

        if (isset($request->order_by)) {
            $orderBy = $request->order_by;
        }

        $query = Film::where('status', $status);

        if (isset($request->genre)) {
            $genre = Genre::where('title', $request->genre)->first();
            $filmIds = $genre->films->modelKeys();
            $query = $query->whereIn('id', $filmIds);
        }

        if (isset($request->order_by) && $request->order_by === 'rating') {
            $orderBy = Comment::selectRaw('avg(c.rating)')->from('comments as c')->whereColumn('c.film_id', 'films.id');
        }

        $query->orderBy($orderBy, $orderTo);

        return $query;
    }

    /**
     * Метод получения похожих (с таким же жанром) фильмов
     * @param  int $id - $id фильма
     *
     * @return array|Arrayable|\JsonSerializable $fourFilmsCollection - массив из случайно отобранных фильмов того же жанра
     */
    public static function createRequestSimilarFilms(int $id): array|Arrayable|\JsonSerializable
    {
        // Количество фильмов с похожим жанром, которое будет извлечено из БД
        $countFilms = 4;

        $film = Film::findOrFail($id);

        $genres = $film->genres;

        $filmsCollection = new Collection();

        foreach ($genres as $genre) {
            $genre = Genre::find($genre->id);

            $films = $genre->films()->select(
                'films.id',
                'name',
                'poster_image',
                'preview_video_link'
            )->inRandomOrder()
                ->take($countFilms)->get();

            $filmsCollection = $filmsCollection->push($films);
        }

        $fourFilmsCollection = $filmsCollection->collapse()->unique('id')->random(4);

        return FilmListResource::collection($fourFilmsCollection)->all();
    }
}
