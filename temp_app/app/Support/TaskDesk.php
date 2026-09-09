<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;

/**
 * The same handoff as CoverageDesk, for the daily boards: marketing raises a
 * task for the multimedia team, it waits in their queue belonging to nobody,
 * and whoever takes it gets it on their own board.
 *
 * Deliberately not auto-assigned. A card dropped on a named person's board
 * without warning is how work gets silently ignored; a queue somebody chooses
 * from leaves a record of who took it.
 */
class TaskDesk
{
    /** Raise a task for a team rather than for a person. */
    public function raiseFor(string $team, array $fields, User $by): Task
    {
        return Task::create($fields + [
            'user_id' => null,
            'for_team' => $team,
            'status' => 'todo',
            'created_by' => $by->id,
        ]);
    }

    /**
     * Hand the task to a named person rather than the shared queue.
     *
     * It is claimed on arrival — nobody has to take it on, because it was given
     * to them — but `for_team` is kept so the board still shows where it came
     * from and "Put back" can return it to the queue.
     */
    public function assignTo(User $person, array $fields, User $by): Task
    {
        return Task::create($fields + [
            'user_id' => $person->id,
            'for_team' => $person->team,
            'claimed_at' => now(),
            'status' => 'todo',
            'created_by' => $by->id,
        ]);
    }

    /**
     * Someone on the team takes the task onto their own board.
     *
     * It lands on the day they claim it, not the day it was raised: a request
     * made last Friday is work for today, and dating it Friday would file it
     * behind the board the claimer is actually looking at.
     */
    public function claim(Task $task, User $by): Task
    {
        $task->update([
            'user_id' => $by->id,
            'task_date' => today(),
            'claimed_at' => now(),
        ]);

        return $task;
    }

    /**
     * Put a claimed task back in the queue — picked up by mistake, or handed on
     * to someone with the right camera.
     */
    public function release(Task $task): Task
    {
        $task->update([
            'user_id' => null,
            'claimed_at' => null,
            'status' => 'todo',
            'completed_at' => null,
        ]);

        return $task;
    }
}
