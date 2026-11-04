<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\UserRole;
use App\Models\ReportCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Datos sintéticos para visualizar el mapa de calor, la bandeja de moderación
 * y las estadísticas. NO usar como datos reales: todo reporte lleva el
 * marcador "[DEMO]" y se puede retirar con DemoDataPurgeSeeder.
 *
 * Uso: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public const MARKER = '[DEMO]';

    public const EMAIL_DOMAIN = 'demo.rutasegura.test';

    private const VERIFIED_REPORTS = 1200;

    private const PENDING_REPORTS = 45;

    private const DISCARDED_REPORTS = 70;

    private const DEMO_USERS = 30;

    private const BATCH_SIZE = 250;

    /**
     * Focos de reportes en el casco urbano: latitud, longitud, dispersión (m),
     * peso relativo y categorías más probables.
     *
     * @var list<array{lat: float, lng: float, sigma: int, weight: int, categories: array<string, int>}>
     */
    private const HOTSPOTS = [
        ['lat' => 4.3018, 'lng' => -74.8037, 'sigma' => 170, 'weight' => 28, 'categories' => ['theft' => 5, 'harassment' => 4, 'pursuit' => 2, 'other' => 1]],
        ['lat' => 4.2990, 'lng' => -74.8010, 'sigma' => 150, 'weight' => 16, 'categories' => ['lighting' => 4, 'harassment' => 3, 'theft' => 2]],
        ['lat' => 4.3120, 'lng' => -74.8070, 'sigma' => 230, 'weight' => 14, 'categories' => ['lighting' => 6, 'low_foot_traffic' => 3, 'pursuit' => 1]],
        ['lat' => 4.3050, 'lng' => -74.8200, 'sigma' => 260, 'weight' => 12, 'categories' => ['low_foot_traffic' => 5, 'lighting' => 4, 'harassment' => 1]],
        ['lat' => 4.2930, 'lng' => -74.8090, 'sigma' => 200, 'weight' => 10, 'categories' => ['harassment' => 4, 'theft' => 3, 'lighting' => 2]],
        ['lat' => 4.3080, 'lng' => -74.7960, 'sigma' => 220, 'weight' => 8, 'categories' => ['lighting' => 4, 'pursuit' => 2, 'other' => 2]],
        ['lat' => 4.3016, 'lng' => -74.8035, 'sigma' => 1500, 'weight' => 12, 'categories' => ['other' => 3, 'harassment' => 2, 'lighting' => 2, 'theft' => 2, 'pursuit' => 1, 'low_foot_traffic' => 2]],
    ];

    /**
     * Peso por hora local (0–23): concentración nocturna.
     *
     * @var list<float>
     */
    private const HOUR_WEIGHTS = [
        1.1, 0.7, 0.4, 0.2, 0.2, 0.2, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1,
        0.1, 0.1, 0.1, 0.1, 0.2, 0.3, 0.6, 0.8, 1.2, 1.5, 1.6, 1.4,
    ];

    /** @var array<string, list<string>> */
    private const DESCRIPTIONS = [
        'lighting' => ['Varias luminarias apagadas en la cuadra.', 'Tramo muy oscuro, casi sin alumbrado.', 'Poste sin luz cerca de la esquina.'],
        'harassment' => ['Comentarios y gestos molestos al pasar.', 'Grupo de personas que incomodó a quienes caminaban.', 'Acoso verbal frente a un local cerrado.'],
        'pursuit' => ['Una persona siguió a otra durante varias cuadras.', 'Seguimiento en moto por la calle principal.'],
        'theft' => ['Intento de robo de celular.', 'Hurto a una peatona cerca de la esquina.', 'Reportan robo de bolso en la zona.'],
        'low_foot_traffic' => ['Calle sin personas ni comercios abiertos.', 'Zona muy sola pasadas las nueve de la noche.'],
        'other' => ['Situación que generó inseguridad en el sector.', 'Aglomeración y desorden en la vía.'],
    ];

    private const DISCARD_REASONS = ['insufficient_information', 'outside_coverage', 'identifying_content', 'duplicate'];

    /** @var list<array{0: float, 1: float}> */
    private array $boundary = [];

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReportCategorySeeder::class,
            GirardotCoverageSeeder::class,
            DemoDataPurgeSeeder::class,
        ]);

        mt_srand(20261104);
        $this->boundary = $this->loadBoundary();

        $categories = ReportCategory::query()->pluck('id', 'code')->all();
        $moderator = $this->createModerator();
        $userIds = $this->createDemoUsers();

        $rows = [];
        $this->appendReports($rows, 'verified', self::VERIFIED_REPORTS, $categories, $moderator->id, $userIds);
        $this->appendReports($rows, 'pending', self::PENDING_REPORTS, $categories, $moderator->id, $userIds);
        $this->appendReports($rows, 'discarded', self::DISCARDED_REPORTS, $categories, $moderator->id, $userIds);

        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            DB::table('reports')->insert($chunk);
        }

        $this->command?->info(sprintf(
            'Datos demo creados: %d reportes (%d verificados, %d pendientes, %d descartados) y %d usuarias.',
            count($rows), self::VERIFIED_REPORTS, self::PENDING_REPORTS, self::DISCARDED_REPORTS, count($userIds),
        ));
    }

    private function createModerator(): User
    {
        return $this->createUser('Moderadora Demo', 'moderadora@'.self::EMAIL_DOMAIN, UserRole::CommunityModerator);
    }

    /**
     * Crea la usuaria sin factories (en producción Faker no está instalado),
     * con una contraseña aleatoria desconocida: nadie puede iniciar sesión.
     */
    private function createUser(string $name, string $email, UserRole $role): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(48))]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);

        return $user;
    }

    /** @return list<int> */
    private function createDemoUsers(): array
    {
        $names = ['Valentina', 'Camila', 'Sofía', 'Daniela', 'Laura', 'Juliana', 'Mariana', 'Paula', 'Natalia', 'Andrea'];
        $surnames = ['Rojas', 'Gómez', 'Díaz', 'Ramírez', 'Torres', 'Castro', 'Vargas', 'Moreno', 'Ortiz', 'Silva'];
        $ids = [];

        for ($i = 1; $i <= self::DEMO_USERS; $i++) {
            $ids[] = $this->createUser(
                $names[$i % 10].' '.$surnames[intdiv($i, 3) % 10],
                sprintf('usuaria%02d@%s', $i, self::EMAIL_DOMAIN),
                UserRole::RegisteredUser,
            )->id;
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $categories
     * @param  list<int>  $userIds
     */
    private function appendReports(array &$rows, string $status, int $count, array $categories, int $moderatorId, array $userIds): void
    {
        $now = CarbonImmutable::now();

        for ($i = 0; $i < $count; $i++) {
            $spot = $this->pickHotspot();
            $categoryCode = $this->pickWeighted($spot['categories']);
            $reason = $status === 'discarded' ? self::DISCARD_REASONS[mt_rand(0, 3)] : null;

            [$lng, $lat] = $reason === 'outside_coverage' ? $this->outsidePoint() : $this->insidePoint($spot);

            $occurredAt = $this->occurredAt($now, $status);
            $createdAt = $occurredAt->addMinutes(mt_rand(5, 180));
            $createdAt = $createdAt->greaterThan($now) ? $now : $createdAt;
            $moderatedAt = $status === 'pending' ? null : $createdAt->addMinutes(mt_rand(30, 2880));
            $moderatedAt = $moderatedAt?->greaterThan($now) ? $now : $moderatedAt;

            $identified = $userIds !== [] && mt_rand(1, 100) <= 18;
            $descriptions = self::DESCRIPTIONS[$categoryCode];

            $rows[] = [
                'id' => (string) Str::uuid(),
                'user_id' => $identified ? $userIds[array_rand($userIds)] : null,
                'report_category_id' => $categories[$categoryCode],
                'is_anonymous' => ! $identified,
                'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%.7F, %.7F), 4326)', $lng, $lat)),
                'occurred_at' => $this->format($occurredAt),
                'description' => self::MARKER.' '.$descriptions[array_rand($descriptions)],
                'status' => $status,
                'moderated_by' => $status === 'pending' ? null : $moderatorId,
                'moderated_at' => $moderatedAt ? $this->format($moderatedAt) : null,
                'moderation_reason' => $reason,
                'created_at' => $this->format($createdAt),
                'updated_at' => $this->format($moderatedAt ?? $createdAt),
            ];
        }
    }

    private function occurredAt(CarbonImmutable $now, string $status): CarbonImmutable
    {
        $daysAgo = match (true) {
            $status === 'pending' => mt_rand(0, 6),
            mt_rand(1, 100) <= 72 => mt_rand(0, 29),
            default => mt_rand(30, 89),
        };

        $local = $now->setTimezone('America/Bogota')->subDays($daysAgo);
        $hour = (int) $this->pickWeighted(array_map(fn (float $weight): int => (int) round($weight * 100), self::HOUR_WEIGHTS));
        $moment = $local->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));

        return $moment->greaterThan($now->subMinutes(10)) ? $now->subMinutes(mt_rand(10, 120)) : $moment;
    }

    /** @return array{lat: float, lng: float, sigma: int, weight: int, categories: array<string, int>} */
    private function pickHotspot(): array
    {
        $weights = array_map(fn (array $spot): int => $spot['weight'], self::HOTSPOTS);

        return self::HOTSPOTS[$this->pickWeighted($weights)];
    }

    /**
     * @param  array<int|string, int>  $weights
     */
    private function pickWeighted(array $weights): int|string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @param  array{lat: float, lng: float, sigma: int}  $spot
     * @return array{0: float, 1: float}
     */
    private function insidePoint(array $spot): array
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $point = $this->gaussianPoint($spot['lat'], $spot['lng'], $spot['sigma']);

            if ($this->isInsideBoundary($point[0], $point[1])) {
                return $point;
            }
        }

        return [$spot['lng'], $spot['lat']];
    }

    /** @return array{0: float, 1: float} */
    private function outsidePoint(): array
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $point = $this->gaussianPoint(4.2830, -74.7800, 4000);

            if (! $this->isInsideBoundary($point[0], $point[1])) {
                return $point;
            }
        }

        return [-74.70, 4.40];
    }

    /** @return array{0: float, 1: float} longitud, latitud */
    private function gaussianPoint(float $lat, float $lng, int $sigmaMeters): array
    {
        $radius = $sigmaMeters * sqrt(-2 * log(max(mt_rand(1, mt_getrandmax()) / mt_getrandmax(), 1e-9)));
        $angle = 2 * M_PI * mt_rand() / mt_getrandmax();

        return [
            $lng + ($radius * sin($angle)) / (111320 * cos(deg2rad($lat))),
            $lat + ($radius * cos($angle)) / 110574,
        ];
    }

    private function isInsideBoundary(float $x, float $y): bool
    {
        $inside = false;
        $count = count($this->boundary);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $this->boundary[$i];
            [$xj, $yj] = $this->boundary[$j];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @return list<array{0: float, 1: float}> */
    private function loadBoundary(): array
    {
        $geojson = json_decode(File::get(database_path('seeders/data/girardot.geojson')), true, flags: JSON_THROW_ON_ERROR);

        return $geojson['features'][0]['geometry']['coordinates'][0];
    }

    private function format(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('Y-m-d H:i:s.uP');
    }
}
