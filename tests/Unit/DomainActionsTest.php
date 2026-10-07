<?php

use App\Actions\Admin\GetAdminOverview;
use App\Actions\Role\UpdateUserRole;
use App\Actions\Session\AddSessionParticipants;
use App\Actions\Session\CancelSession;
use App\Actions\Session\CreateSession;
use App\Actions\Session\DeleteSession;
use App\Actions\Session\FinishSession;
use App\Actions\Session\StartSession;
use App\Actions\Session\UpdateSession;
use App\Actions\Sport\AddCourt;
use App\Actions\Sport\DeleteCourt;
use App\Actions\Sport\DeleteSport;
use App\Actions\Sport\SaveSport;
use App\Actions\User\RegisterMember;
use App\Enums\SessionStatus;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;
use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('policy allows owner and superadmin but denies other admins', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();
    $session = AttendanceSession::factory()->create(['created_by' => $owner->id]);

    expect($owner->can('manage', $session))->toBeTrue();
    expect($other->can('manage', $session))->toBeFalse();
    expect($superadmin->can('manage', $session))->toBeTrue();
});

test('start action rejects non-soon and second active sessions', function () {
    $active = AttendanceSession::factory()->active()->create();
    $soon = AttendanceSession::factory()->create();

    expect(fn () => app(StartSession::class)->handle($active))
        ->toThrow(DomainException::class, 'Hanya sesi berstatus Segera yang dapat dimulai.');

    expect(fn () => app(StartSession::class)->handle($soon))
        ->toThrow(DomainException::class, 'Masih ada sesi aktif lain');
});

test('start action activates session with fresh qr secret', function () {
    $session = AttendanceSession::factory()->create(['qr_secret' => null]);

    $result = app(StartSession::class)->handle($session);

    expect($result->status)->toBe(SessionStatus::Active);
    expect($result->qr_secret)->not->toBeNull();
});

test('create action blocks second active session', function () {
    $admin = User::factory()->admin()->create();
    AttendanceSession::factory()->active()->create();
    $sport = Sport::factory()->create();
    $court = Court::factory()->for($sport)->create();

    expect(fn () => app(CreateSession::class)->handle([
        'name' => 'X',
        'sport_id' => $sport->id,
        'court_id' => $court->id,
        'starts_at' => '2026-10-10 10:00',
        'ends_at' => '2026-10-10 12:00',
    ], $admin))->toThrow(DomainException::class, 'Masih ada sesi aktif');
});

test('update action rejects terminal sessions', function () {
    $session = AttendanceSession::factory()->finished()->create();

    expect(fn () => app(UpdateSession::class)->handle($session, [
        'name' => 'X',
        'sport_id' => $session->sport_id,
        'court_id' => $session->court_id,
        'starts_at' => '2026-10-10 10:00',
        'ends_at' => '2026-10-10 12:00',
    ]))->toThrow(DomainException::class, 'tidak dapat diubah');
});

test('delete action rejects active sessions', function () {
    $session = AttendanceSession::factory()->active()->create();

    expect(fn () => app(DeleteSession::class)->handle($session))
        ->toThrow(DomainException::class, 'tidak dapat dihapus');
});

test('finish and cancel actions reject wrong states', function () {
    $soon = AttendanceSession::factory()->create();

    expect(fn () => app(FinishSession::class)->handle($soon))
        ->toThrow(DomainException::class, 'Hanya sesi aktif yang dapat diselesaikan.');

    expect(fn () => app(CancelSession::class)->handle($soon->refresh(), null))->not->toThrow(DomainException::class);

    $cancelled = AttendanceSession::factory()->cancelled()->create();

    expect(fn () => app(CancelSession::class)->handle($cancelled, null))
        ->toThrow(DomainException::class, 'tidak dapat dibatalkan');
});

test('add participants action rejects busy and duplicate users', function () {
    $admin = User::factory()->admin()->create();
    $busy = User::factory()->create();
    $blocker = AttendanceSession::factory()->create([
        'starts_at' => '2026-10-10 15:00:00',
        'ends_at' => '2026-10-10 18:00:00',
    ]);
    $blocker->participants()->syncWithoutDetaching([$busy->id]);

    $target = AttendanceSession::factory()->create([
        'created_by' => $admin->id,
        'starts_at' => '2026-10-10 16:00:00',
        'ends_at' => '2026-10-10 19:00:00',
    ]);

    expect(fn () => app(AddSessionParticipants::class)->handle($target, [$busy->id]))
        ->toThrow(DomainException::class, 'bentrok jam');

    $target->participants()->syncWithoutDetaching([$busy->id]);

    expect(fn () => app(AddSessionParticipants::class)->handle($target, [$busy->id]))
        ->toThrow(DomainException::class, 'sudah terdaftar');
});

test('sport actions block deletion while in use', function () {
    $sport = Sport::factory()->create();
    $court = Court::factory()->for($sport)->create();
    AttendanceSession::factory()->create(['sport_id' => $sport->id, 'court_id' => $court->id]);

    expect(fn () => app(DeleteCourt::class)->handle($court))
        ->toThrow(DomainException::class, 'masih dipakai sesi');

    expect(fn () => app(DeleteSport::class)->handle($sport))
        ->toThrow(DomainException::class, 'masih dipakai sesi');

    $added = app(AddCourt::class)->handle($sport->id, 'Lapangan Baru');
    expect($added->sport_id)->toBe($sport->id);

    $saved = app(SaveSport::class)->handle(null, 'Tenis');
    expect($saved->name)->toBe('Tenis');
});

test('role action rejects invalid role and self demotion', function () {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->anggota()->create();

    expect(fn () => app(UpdateUserRole::class)->handle($superadmin, $target, 'presiden'))
        ->toThrow(DomainException::class, 'tidak valid');

    expect(fn () => app(UpdateUserRole::class)->handle($superadmin, $superadmin, 'anggota'))
        ->toThrow(DomainException::class, 'akun Anda sendiri');

    app(UpdateUserRole::class)->handle($superadmin, $target, 'admin');
    expect($target->refresh()->isAdmin())->toBeTrue();
});

test('register member creates user with code and anggota role', function () {
    $user = app(RegisterMember::class)->handle([
        'name' => 'Unit Test',
        'email' => 'unit@example.com',
        'password' => 'password123!',
    ]);

    expect($user->member_code)->toMatch('/^GSTN-\d{6}$/');
    expect($user->isAnggota())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
});

test('admin overview returns stats and sessions', function () {
    $overview = app(GetAdminOverview::class);

    expect($overview->stats())->toMatchArray([
        'total_sessions' => 0,
        'total_users' => 0,
        'total_participants' => 0,
    ]);
    expect($overview->activeSession())->toBeNull();
    expect($overview->upcomingSessions())->toBeEmpty();
});
