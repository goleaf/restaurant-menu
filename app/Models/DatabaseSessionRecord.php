<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DatabaseSessionRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Infrastructure rows written by Laravel's session handler. */
final class DatabaseSessionRecord extends Model
{
    /** @use HasFactory<DatabaseSessionRecordFactory> */
    use HasFactory;

    protected $fillable = [];

    protected $hidden = ['id', 'payload'];

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable(): string
    {
        return $this->table ?? (string) config('session.table', 'sessions');
    }

    public function getConnectionName(): ?string
    {
        $connection = $this->connection ?? config('session.connection');

        return is_string($connection) ? $connection : null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'last_activity' => 'integer'];
    }
}
