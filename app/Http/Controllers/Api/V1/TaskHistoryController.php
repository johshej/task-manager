<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TaskHistoryResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskHistoryController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        return TaskHistoryResource::collection(
            $task->history()->get()
        );
    }
}
