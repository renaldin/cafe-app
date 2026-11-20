<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $q = ActivityLog::query()->with('user:id,name')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');
        $p = $q->paginate($this->perPage());
        $p->setCollection($p->getCollection()->map(fn ($l) => [
            'id' => $l->id, 'action' => $l->action, 'description' => $l->description, 'user' => $l->user?->name,
            'subject_type' => $l->subject_type, 'subject_id' => $l->subject_id, 'before' => $l->before, 'after' => $l->after,
            'reason' => $l->reason, 'ip' => $l->ip, 'created_at' => $l->created_at?->toIso8601String(),
        ]));

        return $this->paginated($p);
    }
}
