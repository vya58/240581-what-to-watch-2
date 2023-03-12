<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Responses\ApiSuccessResponse;
use App\Http\Responses\ApiErrorResponse;

class FavoriteController extends Controller
{
    /**
     * Показ фильмов добавленных пользователем в избранное.
     *
     * @return ApiSuccessResponse|ApiErrorResponse
     */
    public function index(/* TO DO User $User */): ApiSuccessResponse|ApiErrorResponse
    {
        return new ApiSuccessResponse();
    }

    /**
     * Добавление фильма в избранное.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return ApiSuccessResponse|ApiErrorResponse
     */
    public function store(Request $request/* TO DO , User $User */): ApiSuccessResponse|ApiErrorResponse
    {
        return new ApiSuccessResponse([], Response::HTTP_CREATED);
    }

    /**
     * Удаление фильма из избранного.
     *
     * @param  int  $id
     * @return ApiSuccessResponse|ApiErrorResponse
     */
    public function destroy($id/* TO DO , User $User */): ApiSuccessResponse|ApiErrorResponse
    {
        return new ApiErrorResponse([], Response::HTTP_UNPROCESSABLE_ENTITY, 'Фильм отсутствует в списке избранного');
    }
}
