<?php

use Illuminate\Support\Facades\File;

it('keeps model mass assignment explicit', function (): void {
    $forbiddenFields = [
        'organization_id',
        'branch_id',
        'role_id',
        'status',
        'public_token',
        'guest_token',
        'created_by_user_id',
    ];
    $violations = [];

    foreach (File::allFiles(app_path('Models')) as $file) {
        $contents = File::get($file->getPathname());

        if (str_contains($contents, 'protected $guarded = []')) {
            $violations[] = $file->getRelativePathname().': uses unbounded guarded array';
        }

        preg_match_all('/#\[Fillable\(\s*\[(.*?)\]\s*\)\]/s', $contents, $matches);

        foreach ($matches[1] as $fillableBody) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $fillableBody, $fieldMatches);

            $blockedFields = array_values(array_intersect($forbiddenFields, $fieldMatches[1]));

            foreach ($blockedFields as $blockedField) {
                $violations[] = $file->getRelativePathname().': '.$blockedField.' is mass assignable';
            }
        }
    }

    expect($violations)->toBeEmpty();
});

it('does not persist request or Livewire public arrays blindly', function (): void {
    $directories = [
        app_path('Actions'),
        app_path('Http'),
        app_path('Livewire'),
    ];

    $patterns = [
        '/(?:create|update|fill)\s*\(\s*\$this->[A-Za-z_][A-Za-z0-9_]*\s*(?:,|\))/m' => 'Livewire public property passed directly to mass assignment',
        '/(?:create|update|fill)\s*\(\s*\$request->(?:all|input|toArray)\s*\(/m' => 'request payload passed directly to mass assignment',
        '/(?:create|update|fill)\s*\(\s*request\(\)->(?:all|input)\s*\(/m' => 'global request payload passed directly to mass assignment',
    ];

    $violations = [];

    foreach ($directories as $directory) {
        foreach (File::allFiles($directory) as $file) {
            $contents = File::get($file->getPathname());

            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $file->getRelativePathname().': '.$message;
                }
            }
        }
    }

    expect($violations)->toBeEmpty();
});
