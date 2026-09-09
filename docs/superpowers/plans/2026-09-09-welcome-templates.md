# Welcome Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** TV phòng có khách chiếu một trong năm mẫu chào cố định; lễ tân chọn lúc nhận phòng/đổi tên; quản lý bật-tắt, mặc định và tên gọi theo khách sạn.

**Architecture:** Catalog là enum PHP (`dusk|linen|harbor|garden|stone`). Mỗi stay lưu `template_key`. Mỗi khách sạn có 5 row `hotel_welcome_templates` + `hotels.default_welcome_template_key`. Player map key → component. CMS không chỉnh layout/màu. Ba git repo riêng: commit task backend trong `hotel-signage-hub-backend`, CMS trong `hotel-signage-hub-cms`, player trong `hotel-signage-hub-player`.

**Tech Stack:** Laravel 13 / PHP 8.3+ / PHPUnit 12 (sqlite memory), Next.js 16 CMS, Vite + React 19 player, Sanctum, Spatie permission, Reverb (payload screen đã có — chỉ thêm field).

## Global Constraints

- Năm mẫu preset. Không tạo mẫu mới, không chỉnh màu/layout theo khách sạn.
- Quản lý template: `hotel-manager` và `super-admin`. Lễ tân chỉ chọn lúc nhận phòng / đổi tên.
- Sau nhận phòng, lễ tân đổi template trong dialog Đổi tên; TV cập nhật realtime.
- Phòng trống: branding khách sạn (`guest: null`, `template: null`).
- Cài đặt (bật/tắt, mặc định, tên gọi) theo khách sạn. Năm layout trên TV dùng chung.
- Dialog nhận phòng/đổi tên chọn sẵn mẫu mặc định (hoặc mẫu đang chạy khi đổi tên).
- Key trên stay + component trong player. Backend không lưu CSS.
- Màn có khách bỏ `media.background_url`. Ảnh KS chỉ dùng màn trống.
- Permission `templates.manage` cho manager + super-admin; GET list dùng `rooms.view`.
- Copy CMS tiếng Việt, ngắn. Không Framer Motion. Không gói npm dùng chung CMS/player.
- Mã phòng `dusk`: dưới cùng trái. `prefers-reduced-motion`: giữ rule toàn cục hiện có trong `hotel-signage-hub-player/src/index.css` (transition 0.01ms) — không thêm exception.
- Spec: `hotel-signage-hub-backend/docs/superpowers/specs/2026-09-09-welcome-templates-design.md`

## Scope

Một plan, ba repo, chạy tuần tự. Backend phải PHPUnit-xanh trước CMS. Không tách ba plan riêng: thiếu một app thì lễ tân chọn mẫu mà TV không đổi.

Working directories:

- Backend: `/Users/edward/Documents/GitHub/hotel-hub/hotel-signage-hub-backend`
- CMS: `/Users/edward/Documents/GitHub/hotel-hub/hotel-signage-hub-cms`
- Player: `/Users/edward/Documents/GitHub/hotel-hub/hotel-signage-hub-player`

Chạy test backend: `php artisan test --filter=ClassName` từ thư mục backend. Sanctum test: `Sanctum::actingAs($user)` như `tests/Feature/StayAndScreenDataTest.php` (không cần abilities).

## File map

**Backend create**

- `app/Domains/Content/WelcomeTemplateKey.php` — backed enum + label + sortOrder
- `app/Domains/Content/WelcomeTemplateCatalog.php` — `syncHotel`, `isEnabled`, `toPayload`
- `app/Models/HotelWelcomeTemplate.php`
- `app/Observers/HotelObserver.php` — `created` → `syncHotel`
- `app/Http/Controllers/Api/Cms/WelcomeTemplateController.php`
- `database/migrations/2026_09_09_000009_add_welcome_templates.php`
- `database/factories/HotelWelcomeTemplateFactory.php`
- `tests/Feature/WelcomeTemplateCatalogTest.php`
- `tests/Feature/WelcomeTemplateApiTest.php`

**Backend modify**

- `app/Models/Hotel.php` — fillable default key, `welcomeTemplates()` hasMany
- `app/Models/WelcomeContent.php` — fillable `template_key`
- `database/factories/HotelFactory.php` — (observer covers create; no extra hook required if observer registered)
- `database/factories/WelcomeContentFactory.php` — default `template_key` `dusk`
- `database/seeders/RoleSeeder.php` — `templates.manage`
- `database/seeders/DemoSeeder.php` — `syncHotel` sau `updateOrCreate` hotel
- `app/Providers/AppServiceProvider.php` — `Hotel::observe`
- `app/Http/Controllers/Api/Cms/HotelController.php` — (observer đủ; không bắt buộc gọi thêm)
- `app/Http/Controllers/Api/Cms/StayController.php` — validate `template_key`
- `app/Domains/Content/StayService.php` — ghi / đổi key
- `app/Domains/Device/ScreenDataBuilder.php` — `template` field
- `routes/api.php` — GET/PATCH templates (route `default` trước `{template}`)
- `tests/Feature/StayAndScreenDataTest.php` — assert `template_key` / screen `template`

**CMS create**

- `src/lib/welcomeTemplates.ts` — keys, built-in labels, token packs, `templateLabel()`
- `src/components/TemplateThumb.tsx` — thumbnail 16:9
- `src/components/TemplatePicker.tsx` — hàng ô chọn
- `src/app/templates/page.tsx`

**CMS modify**

- `src/lib/api.ts` — types
- `src/lib/roles.ts` — `canManageTemplates`
- `src/components/AppShell.tsx` — nav
- `src/components/DeskDialog.tsx` — `wide?: boolean`
- `src/app/rooms/page.tsx` — picker + label

**Player create**

- `src/templates/types.ts`
- `src/templates/VacantWelcome.tsx`
- `src/templates/DuskWelcome.tsx`
- `src/templates/LinenWelcome.tsx`
- `src/templates/HarborWelcome.tsx`
- `src/templates/GardenWelcome.tsx`
- `src/templates/StoneWelcome.tsx`
- `src/templates/OccupiedWelcome.tsx`

**Player modify**

- `src/lib/api.ts` — `template` trên `ScreenData`
- `src/screens/WelcomeScreen.tsx` — router vacant/occupied + banner + crossfade

---

### Task 1: Catalog, schema, sync hotel

**Files:**

- Create: `app/Domains/Content/WelcomeTemplateKey.php`
- Create: `app/Domains/Content/WelcomeTemplateCatalog.php`
- Create: `app/Models/HotelWelcomeTemplate.php`
- Create: `app/Observers/HotelObserver.php`
- Create: `app/Http/Controllers/Api/Cms/WelcomeTemplateController.php` (chưa — Task 2)
- Create: `database/migrations/2026_09_09_000009_add_welcome_templates.php`
- Create: `database/factories/HotelWelcomeTemplateFactory.php`
- Create: `tests/Feature/WelcomeTemplateCatalogTest.php`
- Modify: `app/Models/Hotel.php`
- Modify: `app/Models/WelcomeContent.php`
- Modify: `database/factories/WelcomeContentFactory.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `database/seeders/DemoSeeder.php`

**Interfaces:**

- Consumes: `Hotel::factory()`, `RefreshDatabase`, `RoleSeeder` (đã seed trong `TestCase`)
- Produces: `WelcomeTemplateKey` enum values `dusk|linen|harbor|garden|stone`; `WelcomeTemplateCatalog::syncHotel(Hotel $hotel): void`; `WelcomeTemplateCatalog::isEnabled(Hotel $hotel, string $key): bool`; bảng `hotel_welcome_templates`; cột `hotels.default_welcome_template_key` default `dusk`; cột `welcome_contents.template_key` NOT NULL sau backfill

- [ ] **Step 1: Write the failing test**

Tạo `tests/Feature/WelcomeTemplateCatalogTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Domains\Content\WelcomeTemplateKey;
use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Tests\TestCase;

class WelcomeTemplateCatalogTest extends TestCase
{
    public function test_creating_a_hotel_syncs_five_enabled_templates_default_dusk(): void
    {
        $hotel = Hotel::factory()->create();

        $rows = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(5, $rows);
        $this->assertSame(
            ['dusk', 'linen', 'harbor', 'garden', 'stone'],
            $rows->pluck('template_key')->all(),
        );
        $this->assertTrue($rows->every(fn (HotelWelcomeTemplate $row) => $row->is_enabled));
        $this->assertTrue($rows->every(fn (HotelWelcomeTemplate $row) => $row->display_name === null));
        $this->assertSame('dusk', $hotel->fresh()->default_welcome_template_key);
        $this->assertSame(1, $rows[0]->sort_order);
        $this->assertSame(5, $rows[4]->sort_order);
    }

    public function test_sync_hotel_is_idempotent_and_does_not_reenable(): void
    {
        $hotel = Hotel::factory()->create();

        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'garden')
            ->update(['is_enabled' => false, 'display_name' => 'Vườn']);

        app(WelcomeTemplateCatalog::class)->syncHotel($hotel);

        $this->assertSame(5, HotelWelcomeTemplate::query()->where('hotel_id', $hotel->id)->count());
        $garden = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'garden')
            ->firstOrFail();
        $this->assertFalse($garden->is_enabled);
        $this->assertSame('Vườn', $garden->display_name);
        $this->assertFalse(app(WelcomeTemplateCatalog::class)->isEnabled($hotel, 'garden'));
        $this->assertTrue(app(WelcomeTemplateCatalog::class)->isEnabled($hotel, WelcomeTemplateKey::Dusk->value));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WelcomeTemplateCatalogTest`

Expected: FAIL (class `HotelWelcomeTemplate` / enum / table missing).

- [ ] **Step 3: Write minimal implementation**

`app/Domains/Content/WelcomeTemplateKey.php`:

```php
<?php

namespace App\Domains\Content;

enum WelcomeTemplateKey: string
{
    case Dusk = 'dusk';
    case Linen = 'linen';
    case Harbor = 'harbor';
    case Garden = 'garden';
    case Stone = 'stone';

    public function builtInLabel(): string
    {
        return match ($this) {
            self::Dusk => 'Đêm vàng',
            self::Linen => 'Sáng nhẹ',
            self::Harbor => 'Cảng đêm',
            self::Garden => 'Vườn trà',
            self::Stone => 'Đá ấm',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Dusk => 1,
            self::Linen => 2,
            self::Harbor => 3,
            self::Garden => 4,
            self::Stone => 5,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

`app/Models/HotelWelcomeTemplate.php`:

```php
<?php

namespace App\Models;

use Database\Factories\HotelWelcomeTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelWelcomeTemplate extends Model
{
    /** @use HasFactory<HotelWelcomeTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'template_key',
        'is_enabled',
        'display_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function label(): string
    {
        if (is_string($this->display_name) && $this->display_name !== '') {
            return $this->display_name;
        }

        $key = \App\Domains\Content\WelcomeTemplateKey::tryFrom($this->template_key);

        return $key?->builtInLabel() ?? $this->template_key;
    }
}
```

`database/factories/HotelWelcomeTemplateFactory.php` (factory không bắt buộc cho test này nhưng giữ pattern):

```php
<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HotelWelcomeTemplate> */
class HotelWelcomeTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'template_key' => 'dusk',
            'is_enabled' => true,
            'display_name' => null,
            'sort_order' => 1,
        ];
    }
}
```

Thêm `HasFactory` vào model nếu factory tồn tại.

`app/Domains/Content/WelcomeTemplateCatalog.php`:

```php
<?php

namespace App\Domains\Content;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;

class WelcomeTemplateCatalog
{
    public function syncHotel(Hotel $hotel): void
    {
        foreach (WelcomeTemplateKey::cases() as $key) {
            HotelWelcomeTemplate::query()->firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'template_key' => $key->value,
                ],
                [
                    'is_enabled' => true,
                    'display_name' => null,
                    'sort_order' => $key->sortOrder(),
                ],
            );
        }
    }

    public function isEnabled(Hotel $hotel, string $key): bool
    {
        $this->syncHotel($hotel);

        return HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $key)
            ->where('is_enabled', true)
            ->exists();
    }
}
```

`database/migrations/2026_09_09_000009_add_welcome_templates.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('default_welcome_template_key', 32)->default('dusk');
        });

        Schema::table('welcome_contents', function (Blueprint $table) {
            $table->string('template_key', 32)->default('dusk');
        });

        Schema::create('hotel_welcome_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 32);
            $table->boolean('is_enabled')->default(true);
            $table->string('display_name', 40)->nullable();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
            $table->unique(['hotel_id', 'template_key']);
        });

        $catalog = [
            ['dusk', 'Đêm vàng', 1],
            ['linen', 'Sáng nhẹ', 2],
            ['harbor', 'Cảng đêm', 3],
            ['garden', 'Vườn trà', 4],
            ['stone', 'Đá ấm', 5],
        ];

        $now = now();
        foreach (DB::table('hotels')->pluck('id') as $hotelId) {
            foreach ($catalog as [$key, $name, $order]) {
                DB::table('hotel_welcome_templates')->insert([
                    'hotel_id' => $hotelId,
                    'template_key' => $key,
                    'is_enabled' => true,
                    'display_name' => null,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('hotels')->where('id', $hotelId)->update([
                'default_welcome_template_key' => 'dusk',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_welcome_templates');
        Schema::table('welcome_contents', function (Blueprint $table) {
            $table->dropColumn('template_key');
        });
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('default_welcome_template_key');
        });
    }
};
```

Không dùng `$name` trong insert — catalog PHP mới là nguồn label. (Biến `$name` trong loop migration chỉ để đọc; có thể bỏ tuple thứ hai.)

Sửa `Hotel` fillable: thêm `'default_welcome_template_key'`. Thêm:

```php
public function welcomeTemplates(): HasMany
{
    return $this->hasMany(HotelWelcomeTemplate::class);
}
```

`WelcomeContent` fillable thêm `'template_key'`.

`WelcomeContentFactory` definition thêm `'template_key' => 'dusk'`.

`app/Observers/HotelObserver.php`:

```php
<?php

namespace App\Observers;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Models\Hotel;

class HotelObserver
{
    public function created(Hotel $hotel): void
    {
        app(WelcomeTemplateCatalog::class)->syncHotel($hotel);
    }
}
```

`AppServiceProvider::boot` thêm `Hotel::observe(HotelObserver::class);` (import `App\Models\Hotel` và observer).

`DemoSeeder` sau `updateOrCreate` hotel:

```php
app(\App\Domains\Content\WelcomeTemplateCatalog::class)->syncHotel($hotel);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=WelcomeTemplateCatalogTest`

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Content/WelcomeTemplateKey.php app/Domains/Content/WelcomeTemplateCatalog.php app/Models/HotelWelcomeTemplate.php app/Observers/HotelObserver.php database/migrations/2026_09_09_000009_add_welcome_templates.php database/factories/HotelWelcomeTemplateFactory.php database/factories/WelcomeContentFactory.php app/Models/Hotel.php app/Models/WelcomeContent.php app/Providers/AppServiceProvider.php database/seeders/DemoSeeder.php tests/Feature/WelcomeTemplateCatalogTest.php
git commit -m "$(cat <<'EOF'
Add per-hotel welcome template catalog and sync.

New hotels get five enabled presets and default dusk so check-in can store a template key.
EOF
)"
```

Repo: `hotel-signage-hub-backend`.

---

### Task 2: GET welcome-templates + permission seed

**Files:**

- Create: `app/Http/Controllers/Api/Cms/WelcomeTemplateController.php`
- Create: `tests/Feature/WelcomeTemplateApiTest.php`
- Modify: `database/seeders/RoleSeeder.php`
- Modify: `routes/api.php`
- Modify: `app/Domains/Content/WelcomeTemplateCatalog.php` — thêm `toPayload`

**Interfaces:**

- Consumes: `WelcomeTemplateCatalog::syncHotel`, `HotelWelcomeTemplate::label()`, `WelcomeTemplateKey`
- Produces: `GET /api/cms/hotels/{hotel}/welcome-templates` → `{ data: { default_key: string, templates: list<{key, built_in_name, display_name, label, is_enabled, sort_order}> } }`; receptionist chỉ enabled; manager đủ 5; `WelcomeTemplateCatalog::toPayload(Hotel $hotel, bool $includeDisabled): array`; permission `templates.manage` trên manager + super-admin (dùng Task 3)

- [ ] **Step 1: Write the failing test**

Thêm vào `tests/Feature/WelcomeTemplateApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class WelcomeTemplateApiTest extends TestCase
{
    use CreatesStaff;

    public function test_receptionist_lists_only_enabled_templates(): void
    {
        $hotel = Hotel::factory()->create();
        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'stone')
            ->update(['is_enabled' => false]);

        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $keys = $this->getJson("/api/cms/hotels/{$hotel->id}/welcome-templates")
            ->assertOk()
            ->assertJsonPath('data.default_key', 'dusk')
            ->assertJsonCount(4, 'data.templates')
            ->json('data.templates');
        $this->assertSame(['dusk', 'linen', 'harbor', 'garden'], array_column($keys, 'key'));
    }

    public function test_manager_lists_disabled_templates_too(): void
    {
        $hotel = Hotel::factory()->create();
        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'stone')
            ->update(['is_enabled' => false, 'display_name' => 'Tối đá']);

        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->getJson("/api/cms/hotels/{$hotel->id}/welcome-templates")
            ->assertOk()
            ->assertJsonCount(5, 'data.templates')
            ->assertJsonPath('data.templates.4.key', 'stone')
            ->assertJsonPath('data.templates.4.is_enabled', false)
            ->assertJsonPath('data.templates.4.label', 'Tối đá')
            ->assertJsonPath('data.templates.4.built_in_name', 'Đá ấm');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WelcomeTemplateApiTest`

Expected: FAIL 404 (route missing).

- [ ] **Step 3: Write minimal implementation**

Thêm `toPayload` vào catalog:

```php
/**
 * @return array{default_key: string, templates: list<array<string, mixed>>}
 */
public function toPayload(Hotel $hotel, bool $includeDisabled): array
{
    $this->syncHotel($hotel);
    $hotel->refresh();

    $query = HotelWelcomeTemplate::query()
        ->where('hotel_id', $hotel->id)
        ->orderBy('sort_order');

    if (! $includeDisabled) {
        $query->where('is_enabled', true);
    }

    $templates = $query->get()->map(function (HotelWelcomeTemplate $row) {
        $key = WelcomeTemplateKey::from($row->template_key);

        return [
            'key' => $row->template_key,
            'built_in_name' => $key->builtInLabel(),
            'display_name' => $row->display_name,
            'label' => $row->label(),
            'is_enabled' => $row->is_enabled,
            'sort_order' => $row->sort_order,
        ];
    })->values()->all();

    return [
        'default_key' => $hotel->default_welcome_template_key,
        'templates' => $templates,
    ];
}
```

Controller:

```php
<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Domains\Content\WelcomeTemplateKey;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WelcomeTemplateController extends Controller
{
    public function __construct(private WelcomeTemplateCatalog $catalog) {}

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.view'), 403);

        $includeDisabled = $request->user()->can('templates.manage');

        return response()->json([
            'data' => $this->catalog->toPayload($hotel, $includeDisabled),
        ]);
    }

    public function update(Request $request, Hotel $hotel, string $template): JsonResponse
    {
        abort_unless($request->user()?->can('templates.manage'), 403);
        $this->catalog->syncHotel($hotel);

        if (WelcomeTemplateKey::tryFrom($template) === null) {
            abort(404);
        }

        $data = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        $row = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $template)
            ->firstOrFail();

        if (array_key_exists('display_name', $data) && $data['display_name'] === '') {
            $data['display_name'] = null;
        }

        if (array_key_exists('is_enabled', $data) && $data['is_enabled'] === false) {
            if ($hotel->default_welcome_template_key === $template) {
                throw ValidationException::withMessages([
                    'is_enabled' => 'Không thể tắt mẫu mặc định.',
                ]);
            }
            $enabled = HotelWelcomeTemplate::query()
                ->where('hotel_id', $hotel->id)
                ->where('is_enabled', true)
                ->count();
            if ($enabled <= 1 && $row->is_enabled) {
                throw ValidationException::withMessages([
                    'is_enabled' => 'Không thể tắt mẫu cuối cùng.',
                ]);
            }
        }

        $row->fill($data);
        $row->save();

        return response()->json(['data' => $this->catalog->toPayload($hotel, true)]);
    }

    public function updateDefault(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('templates.manage'), 403);
        $this->catalog->syncHotel($hotel);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(WelcomeTemplateKey::values())],
        ]);

        if (! $this->catalog->isEnabled($hotel, $data['key'])) {
            throw ValidationException::withMessages([
                'key' => 'Mẫu mặc định phải đang bật.',
            ]);
        }

        $hotel->forceFill(['default_welcome_template_key' => $data['key']])->save();

        return response()->json(['data' => $this->catalog->toPayload($hotel->fresh(), true)]);
    }
}
```

Task 2 chỉ cần `index`. Có thể để `update`/`updateDefault` trong cùng file nhưng **chưa gắn route PATCH** cho đến Task 3 — hoặc gắn luôn vì test Task 2 không gọi PATCH. Gắn GET only ở Task 2.

`RoleSeeder`: thêm `'templates.manage'` vào `PERMISSIONS` và vào `syncPermissions` của `super-admin` (đã all) và `hotel-manager`. Không thêm cho `receptionist`.

`routes/api.php` trong group `hotels/{hotel}`:

```php
Route::get('welcome-templates', [WelcomeTemplateController::class, 'index']);
```

Import controller.

Gate::before: super-admin đã bypass mọi ability. Manager cần permission thật.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=WelcomeTemplateApiTest`

Expected: PASS.

Cũng chạy: `php artisan test --filter=StaffManagementTest` — RoleSeeder đổi permission list không được làm fail staff tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Cms/WelcomeTemplateController.php app/Domains/Content/WelcomeTemplateCatalog.php database/seeders/RoleSeeder.php routes/api.php tests/Feature/WelcomeTemplateApiTest.php
git commit -m "$(cat <<'EOF'
Add hotel welcome-template list API.

Desk staff can read enabled presets; managers see disabled rows and custom labels.
EOF
)"
```

---

### Task 3: PATCH template row and default

**Files:**

- Modify: `tests/Feature/WelcomeTemplateApiTest.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/Cms/WelcomeTemplateController.php` (nếu chưa có update ở Task 2)

**Interfaces:**

- Consumes: `WelcomeTemplateController::update`, `updateDefault`, `templates.manage`
- Produces: `PATCH /welcome-templates/default` body `{ key }` (khai báo **trước** `{template}`); `PATCH /welcome-templates/{template}` body `{ is_enabled?, display_name? }`; 403 receptionist; 422 tắt default / tắt cuối / default key tắt

- [ ] **Step 1: Write the failing tests**

Append vào `WelcomeTemplateApiTest`:

```php
    public function test_receptionist_cannot_patch_templates(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'is_enabled' => false,
        ])->assertForbidden();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'linen',
        ])->assertForbidden();
    }

    public function test_manager_renames_and_disables_non_default_template(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/garden", [
            'display_name' => 'Trà sen',
            'is_enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.templates.3.label', 'Trà sen')
            ->assertJsonPath('data.templates.3.is_enabled', false);
    }

    public function test_cannot_disable_default_or_last_enabled_template(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/dusk", [
            'is_enabled' => false,
        ])->assertStatus(422);

        foreach (['linen', 'harbor', 'garden', 'stone'] as $key) {
            $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/{$key}", [
                'is_enabled' => false,
            ])->assertOk();
        }

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/dusk", [
            'is_enabled' => false,
        ])->assertStatus(422);
    }

    public function test_manager_sets_default_to_enabled_key_only(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'harbor',
        ])
            ->assertOk()
            ->assertJsonPath('data.default_key', 'harbor');

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/stone", [
            'is_enabled' => false,
        ])->assertOk();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'stone',
        ])->assertStatus(422);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/default", [
            'key' => 'not-a-template',
        ])->assertStatus(422);
    }

    public function test_empty_display_name_clears_to_built_in_label(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs($this->staff('hotel-manager', $hotel));

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'display_name' => 'Sáng',
        ])->assertOk();

        $this->patchJson("/api/cms/hotels/{$hotel->id}/welcome-templates/linen", [
            'display_name' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.templates.1.display_name', null)
            ->assertJsonPath('data.templates.1.label', 'Sáng nhẹ');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_receptionist_cannot_patch_templates`

Expected: FAIL 404 nếu chưa có route PATCH.

- [ ] **Step 3: Wire routes (controller methods đã có ở Task 2)**

Trong `routes/api.php`, **thứ tự**:

```php
Route::get('welcome-templates', [WelcomeTemplateController::class, 'index']);
Route::patch('welcome-templates/default', [WelcomeTemplateController::class, 'updateDefault']);
Route::patch('welcome-templates/{template}', [WelcomeTemplateController::class, 'update']);
```

Nếu Task 2 chưa copy `update`/`updateDefault` vào controller, copy nguyên class Task 2 Step 3.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=WelcomeTemplateApiTest`

Expected: PASS tất cả method trong class.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php app/Http/Controllers/Api/Cms/WelcomeTemplateController.php tests/Feature/WelcomeTemplateApiTest.php
git commit -m "$(cat <<'EOF'
Add welcome-template enable, rename, and default endpoints.

Managers cannot disable the hotel default or the last remaining preset.
EOF
)"
```

---

### Task 4: Stay template_key and screen payload

**Files:**

- Modify: `app/Domains/Content/StayService.php`
- Modify: `app/Http/Controllers/Api/Cms/StayController.php`
- Modify: `app/Domains/Device/ScreenDataBuilder.php`
- Modify: `tests/Feature/StayAndScreenDataTest.php`

**Interfaces:**

- Consumes: `WelcomeTemplateCatalog::syncHotel`, `isEnabled`; `WelcomeTemplateKey::values()`; `hotels.default_welcome_template_key`
- Produces: `WelcomeContent.template_key` string; check-in body optional `template_key`; PATCH welcome optional `template_key`; screen `{ template: { key: string } | null }`; 422 disabled key on check-in và khi đổi sang key khác đang tắt; giữ key hiện tại nếu mẫu vừa tắt

- [ ] **Step 1: Write the failing tests**

Thêm vào `StayAndScreenDataTest` (giữ test cũ; bổ sung assert `template_key` vào test check-in đầu nếu cần — test đầu `postJson` không gửi key → expect `dusk`).

Sửa `test_check_in_creates_stay_bumps_revision_and_broadcasts` thêm:

```php
->assertJsonPath('data.template_key', 'dusk');
```

Thêm các test:

```php
    public function test_check_in_stores_explicit_template_and_screen_payload(): void
    {
        Event::fake([RoomContentUpdated::class]);

        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = \App\Models\Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Mai',
            'template_key' => 'linen',
        ])
            ->assertCreated()
            ->assertJsonPath('data.template_key', 'linen');

        Event::assertDispatched(RoomContentUpdated::class, function (RoomContentUpdated $event) {
            return ($event->payload['template']['key'] ?? null) === 'linen'
                && $event->payload['guest']['display_name'] === 'Mai';
        });

        Sanctum::actingAs($device);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('template.key', 'linen')
            ->assertJsonPath('guest.display_name', 'Mai');
    }

    public function test_check_in_rejects_disabled_template_and_creates_no_stay(): void
    {
        $hotel = Hotel::factory()->create();
        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'garden')
            ->update(['is_enabled' => false]);
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Mai',
            'template_key' => 'garden',
        ])->assertStatus(422);

        $this->assertDatabaseCount('welcome_contents', 0);
        $this->assertNull($room->fresh()->current_welcome_id);
    }

    public function test_update_welcome_changes_template_but_keeps_disabled_current(): void
    {
        Event::fake([RoomContentUpdated::class]);
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $user = $this->staff('receptionist', $hotel);
        Sanctum::actingAs($user);

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Mai',
            'template_key' => 'garden',
        ])->assertCreated();

        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'garden')
            ->update(['is_enabled' => false]);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/welcome", [
            'guest_display_name' => 'Mai Lan',
        ])
            ->assertOk()
            ->assertJsonPath('data.template_key', 'garden');

        $this->patchJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/welcome", [
            'template_key' => 'garden',
        ])
            ->assertOk()
            ->assertJsonPath('data.template_key', 'garden');

        $this->patchJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/welcome", [
            'template_key' => 'stone',
        ])
            ->assertOk()
            ->assertJsonPath('data.template_key', 'stone');

        HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', 'linen')
            ->update(['is_enabled' => false]);

        $this->patchJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/welcome", [
            'template_key' => 'linen',
        ])->assertStatus(422);
    }

    public function test_checkout_screen_has_null_template(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        $device = \App\Models\Device::factory()->paired($hotel, $room)->create();
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Mai',
            'template_key' => 'harbor',
        ])->assertCreated();

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/checkout")->assertOk();

        Sanctum::actingAs($device);
        $this->getJson('/api/device/screen')
            ->assertOk()
            ->assertJsonPath('guest', null)
            ->assertJsonPath('template', null);
    }

    public function test_check_in_unknown_template_key_is_rejected(): void
    {
        $hotel = Hotel::factory()->create();
        $room = Room::factory()->create(['hotel_id' => $hotel->id]);
        Sanctum::actingAs($this->staff('receptionist', $hotel));

        $this->postJson("/api/cms/hotels/{$hotel->id}/rooms/{$room->id}/check-in", [
            'guest_display_name' => 'Mai',
            'template_key' => 'neon',
        ])->assertStatus(422);
    }
```

Import `HotelWelcomeTemplate` và `RoomContentUpdated` (file đã import event).

Sửa `test_checkout_clears_guest_and_screen_shows_hotel_branding` thêm `->assertJsonPath('template', null)`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_check_in_stores_explicit_template`

Expected: FAIL (validation strips unknown field / no `template` in screen).

- [ ] **Step 3: Implement StayService + StayController + ScreenDataBuilder**

`StayController` validate check-in thêm:

```php
'template_key' => ['nullable', 'string', Rule::in(WelcomeTemplateKey::values())],
```

'template_key' => ['sometimes', 'required', 'string', Rule::in(WelcomeTemplateKey::values())],

Import `Rule` và `WelcomeTemplateKey`.

`StayService::checkIn` trong transaction, sau `lockForUpdate`, trước `create`:

```php
$hotel = $room->hotel;
app(WelcomeTemplateCatalog::class)->syncHotel($hotel);
$hotel->refresh();
$key = $data['template_key'] ?? $hotel->default_welcome_template_key;
$this->assertTemplateEnabled($hotel, $key);
```

Trong `create([...])` thêm `'template_key' => $key`.

`StayService::updateCurrent`:

```php
app(WelcomeTemplateCatalog::class)->syncHotel($room->hotel);
$room->hotel->refresh();

if (array_key_exists('template_key', $data) && $data['template_key'] !== null) {
    $next = $data['template_key'];
    if ($next !== $stay->template_key) {
        $this->assertTemplateEnabled($room->hotel, $next);
    }
}

$stay->fill($data);
```

Private method:

```php
private function assertTemplateEnabled(\App\Models\Hotel $hotel, string $key): void
{
    if (! app(WelcomeTemplateCatalog::class)->isEnabled($hotel, $key)) {
        throw ValidationException::withMessages([
            'template_key' => 'Mẫu này không khả dụng.',
        ]);
    }
}
```

`ScreenDataBuilder::forRoom` — trong return array thêm:

```php
'template' => $stay ? [
    'key' => $stay->template_key,
] : null,
```

Cập nhật phpdoc `@param` của `checkIn`/`updateCurrent` thêm `template_key`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=StayAndScreenDataTest`

Expected: PASS (cũ + mới).

Run: `php artisan test`

Expected: PASS toàn bộ suite.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Content/StayService.php app/Http/Controllers/Api/Cms/StayController.php app/Domains/Device/ScreenDataBuilder.php tests/Feature/StayAndScreenDataTest.php
git commit -m "$(cat <<'EOF'
Store template_key on stays and expose it on screen data.

Check-in defaults to the hotel preset; disabled keys are rejected except keeping a stay already on that key.
EOF
)"
```

---

### Task 5: CMS catalog helpers, nav, templates page

**Files:**

- Create: `src/lib/welcomeTemplates.ts`
- Create: `src/components/TemplateThumb.tsx`
- Create: `src/app/templates/page.tsx`
- Modify: `src/lib/api.ts`
- Modify: `src/lib/roles.ts`
- Modify: `src/components/AppShell.tsx`

**Interfaces:**

- Consumes: `GET /cms/hotels/{id}/welcome-templates`, `PATCH .../welcome-templates/{key}`, `PATCH .../welcome-templates/default`
- Produces: `WelcomeTemplateKey`, `BUILTIN_LABELS`, `TEMPLATE_TOKENS`, `templateLabel()`, `canManageTemplates()`, page `/templates`

Không có PHPUnit. Verify tay ở Step 4.

- [ ] **Step 1: Add shared CMS catalog (no test runner)**

`src/lib/welcomeTemplates.ts`:

```ts
export const WELCOME_TEMPLATE_KEYS = ["dusk", "linen", "harbor", "garden", "stone"] as const;
export type WelcomeTemplateKey = (typeof WELCOME_TEMPLATE_KEYS)[number];

export const BUILTIN_LABELS: Record<WelcomeTemplateKey, string> = {
  dusk: "Đêm vàng",
  linen: "Sáng nhẹ",
  harbor: "Cảng đêm",
  garden: "Vườn trà",
  stone: "Đá ấm",
};

export type TemplateTokens = {
  bg: string;
  ink: string;
  muted: string;
  name: string;
  accent?: string;
  panel?: string;
  band?: string;
  rule?: string;
};

export const TEMPLATE_TOKENS: Record<WelcomeTemplateKey, TemplateTokens> = {
  dusk: {
    bg: "oklch(0.10 0 0)",
    ink: "oklch(0.94 0.012 110)",
    muted: "oklch(0.68 0.02 110)",
    name: "oklch(0.78 0.11 110)",
    accent: "oklch(0.62 0.07 230)",
  },
  linen: {
    bg: "oklch(0.97 0.012 85)",
    ink: "oklch(0.28 0.035 55)",
    muted: "oklch(0.48 0.02 55)",
    name: "oklch(0.38 0.08 45)",
    rule: "oklch(0.82 0.03 75)",
  },
  harbor: {
    bg: "oklch(0.16 0.028 230)",
    ink: "oklch(0.93 0.015 95)",
    muted: "oklch(0.72 0.03 220)",
    name: "oklch(0.88 0.04 95)",
    accent: "oklch(0.70 0.06 200)",
    panel: "oklch(0.12 0.032 230)",
  },
  garden: {
    bg: "oklch(0.93 0.022 140)",
    ink: "oklch(0.32 0.04 55)",
    muted: "oklch(0.45 0.03 145)",
    name: "oklch(0.34 0.07 145)",
  },
  stone: {
    bg: "oklch(0.11 0.012 55)",
    ink: "oklch(0.93 0.02 80)",
    muted: "oklch(0.66 0.02 55)",
    name: "oklch(0.91 0.025 85)",
    accent: "oklch(0.72 0.08 75)",
    band: "oklch(0.15 0.016 55)",
  },
};

export function isWelcomeTemplateKey(value: string): value is WelcomeTemplateKey {
  return (WELCOME_TEMPLATE_KEYS as readonly string[]).includes(value);
}

export function templateLabel(
  key: string,
  templates: { key: string; label: string }[],
): string {
  const row = templates.find((t) => t.key === key);
  if (row) return row.label;
  if (isWelcomeTemplateKey(key)) return BUILTIN_LABELS[key];
  return key;
}
```

`src/lib/api.ts` thêm:

```ts
export type WelcomeTemplate = {
  key: string;
  built_in_name: string;
  display_name: string | null;
  label: string;
  is_enabled: boolean;
  sort_order: number;
};

export type WelcomeTemplateList = {
  default_key: string;
  templates: WelcomeTemplate[];
};
```

Trên `Room.current_welcome` thêm `template_key?: string | null`.

`src/lib/roles.ts`:

```ts
export function canManageTemplates(user: CmsUser | null): boolean {
  return Boolean(user?.roles.some((role) => role === "hotel-manager" || role === "super-admin"));
}
```

- [ ] **Step 2: Thumbnail component**

`src/components/TemplateThumb.tsx`:

```tsx
"use client";

import { TEMPLATE_TOKENS, type WelcomeTemplateKey, isWelcomeTemplateKey } from "@/lib/welcomeTemplates";

type Props = {
  templateKey: string;
  selected?: boolean;
  className?: string;
};

export function TemplateThumb({ templateKey, selected = false, className = "" }: Props) {
  const key: WelcomeTemplateKey = isWelcomeTemplateKey(templateKey) ? templateKey : "dusk";
  const t = TEMPLATE_TOKENS[key];

  return (
    <div
      className={`relative overflow-hidden rounded-[8px] border ${
        selected ? "border-primary" : "border-line"
      } ${className}`}
      style={{ aspectRatio: "16 / 9", background: t.bg, color: t.ink }}
      aria-hidden
    >
      {key === "harbor" ? (
        <div className="absolute inset-y-0 left-0 w-[42%]" style={{ background: t.panel }} />
      ) : null}
      {key === "stone" ? (
        <div className="absolute inset-x-0 bottom-0 h-[32%]" style={{ background: t.band }} />
      ) : null}
      <div className="absolute inset-0 flex flex-col justify-between p-2 text-[7px] leading-tight">
        <span style={{ color: t.muted }}>KS</span>
        <span className="font-medium" style={{ color: t.name }}>
          Nguyễn Văn A
        </span>
        <span style={{ color: t.accent ?? t.muted }}>101</span>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Templates page + nav**

`src/app/templates/page.tsx` — pattern `staff/page.tsx`: `AppShell`, `useSession`, `canManageTemplates`, nếu không được phép hiện một câu “Chỉ quản lý được sửa mẫu chào.”; nếu được, GET list, hàng 5 mẫu.

Logic chính:

```tsx
"use client";

import { useCallback, useEffect, useState } from "react";
import { AppShell } from "@/components/AppShell";
import { TemplateThumb } from "@/components/TemplateThumb";
import { api, ApiError, type WelcomeTemplate, type WelcomeTemplateList } from "@/lib/api";
import { canManageTemplates } from "@/lib/roles";
import { useSession } from "@/lib/session";

export default function TemplatesPage() {
  const { hotelId, ready, user } = useSession();
  const allowed = canManageTemplates(user);
  const [list, setList] = useState<WelcomeTemplateList | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busyKey, setBusyKey] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!hotelId || !allowed) return;
    try {
      const res = await api<{ data: WelcomeTemplateList }>(`/cms/hotels/${hotelId}/welcome-templates`);
      setList(res.data);
      setError(null);
    } catch {
      setError("Không tải được danh sách mẫu.");
      setList({ default_key: "dusk", templates: [] });
    }
  }, [hotelId, allowed]);

  useEffect(() => {
    if (ready && user && hotelId && allowed) void load();
  }, [ready, user, hotelId, allowed, load]);

  async function patchRow(key: string, body: { is_enabled?: boolean; display_name?: string | null }) {
    if (!hotelId) return;
    setBusyKey(key);
    try {
      const res = await api<{ data: WelcomeTemplateList }>(`/cms/hotels/${hotelId}/welcome-templates/${key}`, {
        method: "PATCH",
        body: JSON.stringify(body),
      });
      setList(res.data);
      setError(null);
    } catch (err) {
      setError(err instanceof ApiError ? "Không lưu được mẫu. Kiểm tra mẫu mặc định và mẫu cuối còn bật." : "Lỗi mạng.");
    } finally {
      setBusyKey(null);
    }
  }

  async function setDefault(key: string) {
    if (!hotelId) return;
    setBusyKey(key);
    try {
      const res = await api<{ data: WelcomeTemplateList }>(`/cms/hotels/${hotelId}/welcome-templates/default`, {
        method: "PATCH",
        body: JSON.stringify({ key }),
      });
      setList(res.data);
      setError(null);
    } catch {
      setError("Không đặt được mẫu mặc định.");
    } finally {
      setBusyKey(null);
    }
  }

  return (
    <AppShell>
      <main className="px-5 py-6 lg:px-10">
        <h1 className="text-2xl font-medium tracking-tight">Mẫu chào</h1>
        <p className="mt-1 text-sm text-muted">Chọn mẫu mặc định và mẫu lễ tân được dùng lúc nhận phòng.</p>
        {error ? <p className="mt-4 text-sm text-danger">{error}</p> : null}
        {!allowed ? (
          <p className="mt-6 text-sm text-muted">Chỉ quản lý được sửa mẫu chào.</p>
        ) : !hotelId ? (
          <p className="mt-6 text-sm text-muted">Chưa chọn khách sạn.</p>
        ) : (
          <ul className="mt-6 divide-y divide-line border-y border-line">
            {(list?.templates ?? []).map((row: WelcomeTemplate) => {
              const isDefault = list?.default_key === row.key;
              return (
                <li key={row.key} className="grid gap-4 py-4 md:grid-cols-[240px_minmax(0,1fr)_auto] md:items-center">
                  <TemplateThumb templateKey={row.key} />
                  <div className="space-y-2">
                    <label className="block space-y-1.5">
                      <span className="text-sm font-medium">Tên gọi</span>
                      <input
                        defaultValue={row.display_name ?? ""}
                        placeholder={row.built_in_name}
                        maxLength={40}
                        className="w-full rounded-[10px] border border-line px-3 py-2"
                        disabled={busyKey === row.key}
                        onBlur={(e) => {
                          const next = e.target.value.trim();
                          const prev = row.display_name ?? "";
                          if (next === prev) return;
                          void patchRow(row.key, { display_name: next });
                        }}
                      />
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={row.is_enabled}
                        disabled={busyKey === row.key || isDefault}
                        onChange={(e) => void patchRow(row.key, { is_enabled: e.target.checked })}
                      />
                      Bật cho lễ tân
                    </label>
                  </div>
                  <button
                    type="button"
                    disabled={!row.is_enabled || isDefault || busyKey === row.key}
                    onClick={() => void setDefault(row.key)}
                    className="rounded-[10px] border border-line px-3 py-1.5 text-sm disabled:opacity-50"
                  >
                    {isDefault ? "Đang mặc định" : "Mặc định"}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </main>
    </AppShell>
  );
}
```

Checkbox default: disabled khi `isDefault` (không tắt default). Nút Mặc định disabled khi `!row.is_enabled`.

`AppShell` import `FrameCorners` từ `@phosphor-icons/react`. Trong `links` array, sau Phòng:

```ts
...(canManageTemplates(user) ? [{ href: "/templates", label: "Mẫu chào", icon: FrameCorners }] : []),
```

Import `canManageTemplates`.

- [ ] **Step 4: Verify in browser**

Chạy CMS `npm run dev`. Login `manager@saigon-pearl.test` / `password`. Mở `/templates`. Đổi tên một mẫu, tắt `stone`, đặt default `harbor`. Login `desk@saigon-pearl.test` — không thấy nav Mẫu chào.

- [ ] **Step 5: Commit** (repo CMS)

```bash
git add src/lib/welcomeTemplates.ts src/components/TemplateThumb.tsx src/app/templates/page.tsx src/lib/api.ts src/lib/roles.ts src/components/AppShell.tsx
git commit -m "$(cat <<'EOF'
Add hotel template management desk.

Managers rename, enable, and set the default welcome scene without a layout editor.
EOF
)"
```

---

### Task 6: CMS check-in picker and room list label

**Files:**

- Create: `src/components/TemplatePicker.tsx`
- Modify: `src/components/DeskDialog.tsx`
- Modify: `src/app/rooms/page.tsx`

**Interfaces:**

- Consumes: `TemplateThumb`, `GET welcome-templates`, `current_welcome.template_key`, `templateLabel()`
- Produces: dialog nhận phòng/đổi tên gửi `template_key`; preselect default hoặc stay hiện tại; union enabled ∪ current key; hàng phòng hiện label

- [ ] **Step 1: Widen dialog**

`DeskDialog.tsx` thêm `wide?: boolean`. Form/div class:

```tsx
className={`w-full space-y-4 rounded-[10px] bg-bg p-5 ${wide ? "max-w-2xl" : "max-w-md"}`}
```

- [ ] **Step 2: Picker**

`src/components/TemplatePicker.tsx`:

```tsx
"use client";

import { TemplateThumb } from "@/components/TemplateThumb";
import type { WelcomeTemplate } from "@/lib/api";

type Props = {
  templates: WelcomeTemplate[];
  value: string;
  onChange: (key: string) => void;
};

export function TemplatePicker({ templates, value, onChange }: Props) {
  return (
    <fieldset className="space-y-2">
      <legend className="text-sm font-medium">Mẫu trên TV</legend>
      <div className="flex flex-wrap gap-2">
        {templates.map((row) => {
          const selected = row.key === value;
          return (
            <button
              key={row.key}
              type="button"
              onClick={() => onChange(row.key)}
              className={`w-[7.5rem] text-left ${selected ? "" : "opacity-80"}`}
            >
              <TemplateThumb templateKey={row.key} selected={selected} />
              <span className="mt-1 block text-xs text-muted">{row.label}</span>
            </button>
          );
        })}
      </div>
    </fieldset>
  );
}
```

- [ ] **Step 3: Rooms page**

State thêm:

```ts
const [templateKey, setTemplateKey] = useState("dusk");
const [templateList, setTemplateList] = useState<WelcomeTemplateList | null>(null);
```

`load` sau khi load rooms, GET `/cms/hotels/${hotelId}/welcome-templates` (lễ tân được `rooms.view`). Lưu `setTemplateList`.

`open("checkin"|"rename")`:

```ts
const enabled = templateList?.templates ?? [];
if (next === "checkin") {
  setTemplateKey(templateList?.default_key ?? "dusk");
} else {
  const current = room.current_welcome?.template_key ?? templateList?.default_key ?? "dusk";
  setTemplateKey(current);
}
```

Picker options:

```ts
function pickerTemplates(): WelcomeTemplate[] {
  const rows = templateList?.templates ?? [];
  if (mode === "rename" && active?.current_welcome?.template_key) {
    const current = active.current_welcome.template_key;
    if (!rows.some((r) => r.key === current)) {
      return [
        ...rows,
        {
          key: current,
          built_in_name: templateLabel(current, []),
          display_name: null,
          label: templateLabel(current, []),
          is_enabled: false,
          sort_order: 99,
        },
      ];
    }
  }
  return rows;
}
```

Check-in POST body thêm `template_key: templateKey`. Rename PATCH thêm `template_key: templateKey`.

`DeskDialog` `wide={mode === "checkin" || mode === "rename"}`. Trong children chế độ tên/thông điệp, dưới message:

```tsx
<TemplatePicker templates={pickerTemplates()} value={templateKey} onChange={setTemplateKey} />
```

Hàng phòng occupied, dưới tên khách:

```tsx
<p className="text-xs text-muted">
  {templateLabel(room.current_welcome?.template_key ?? "", templateList?.templates ?? [])}
</p>
```

Chỉ hiện khi `template_key` truthy.

- [ ] **Step 4: Verify in browser**

Lễ tân: Nhận phòng 101 — ô default (harbor nếu Task 5 đổi) đã chọn. Lưu. Hàng phòng hiện label. Đổi tên → đổi `linen`. Manager tắt `stone` — lễ tân không thấy `stone` lúc nhận phòng mới.

- [ ] **Step 5: Commit** (repo CMS)

```bash
git add src/components/TemplatePicker.tsx src/components/DeskDialog.tsx src/app/rooms/page.tsx
git commit -m "$(cat <<'EOF'
Let the desk pick a welcome template at check-in.

Rename can change the scene; occupied rooms show the template label.
EOF
)"
```

---

### Task 7: Player vacant/occupied scenes

**Files:**

- Create: `src/templates/types.ts`
- Create: `src/templates/VacantWelcome.tsx`
- Create: `src/templates/DuskWelcome.tsx`
- Create: `src/templates/LinenWelcome.tsx`
- Create: `src/templates/HarborWelcome.tsx`
- Create: `src/templates/GardenWelcome.tsx`
- Create: `src/templates/StoneWelcome.tsx`
- Create: `src/templates/OccupiedWelcome.tsx`
- Modify: `src/lib/api.ts`
- Modify: `src/screens/WelcomeScreen.tsx`

**Interfaces:**

- Consumes: `ScreenData.template: { key: string } | null`, `ScreenData.guest`
- Produces: vacant giữ branding + `background_url`; occupied 5 component, không đọc `background_url`; key lạ → `dusk`; crossfade 280ms khi đổi key

- [ ] **Step 1: Extend ScreenData**

Trong `src/lib/api.ts`:

```ts
  guest: {
    display_name: string;
    message: string | null;
    locale: string;
  } | null;
  template: { key: string } | null;
  media: {
    background_url: string | null;
  };
```

- [ ] **Step 2: Types + vacant**

`src/templates/types.ts`:

```ts
import type { ScreenData } from "../lib/api";

export type OccupiedProps = {
  hotel: ScreenData["hotel"];
  room: ScreenData["room"];
  guest: NonNullable<ScreenData["guest"]>;
};

export const NAME_CLASS =
  "text-balance font-medium text-[clamp(2.75rem,8vw,6.5rem)] leading-[1.05] tracking-[-0.03em]";
```

`src/templates/VacantWelcome.tsx`:

```tsx
import type { ScreenData } from "../lib/api";
import { NAME_CLASS } from "./types";

export function VacantWelcome({ screen }: { screen: ScreenData }) {
  const bg = screen.media.background_url;

  return (
    <div className="relative flex min-h-[100dvh] flex-1 flex-col overflow-hidden">
      {bg ? <img src={bg} alt="" className="absolute inset-0 size-full object-cover" /> : null}
      <div className={`absolute inset-0 ${bg ? "bg-bg/80" : "bg-bg"}`} />
      <div className="relative flex flex-1 flex-col justify-between px-10 py-12 md:px-20">
        <div className="flex items-center gap-4">
          {screen.hotel.logo_url ? (
            <img src={screen.hotel.logo_url} alt="" className="h-10 w-auto" />
          ) : null}
          <p className="text-sm text-muted">{screen.hotel.name}</p>
        </div>
        <div className="max-w-[18ch]">
          <p className={`${NAME_CLASS} text-ink`}>{screen.hotel.name}</p>
          <p className="mt-6 text-xl text-muted">Chào mừng quý khách</p>
        </div>
        <p className="text-sm tracking-[0.18em] text-accent uppercase">{screen.room.code}</p>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Five occupied components**

`src/templates/DuskWelcome.tsx`:

```tsx
import { useEffect, useState } from "react";
import { NAME_CLASS, type OccupiedProps } from "./types";

export function DuskWelcome({ hotel, room, guest }: OccupiedProps) {
  const [inView, setInView] = useState(false);
  useEffect(() => {
    const id = requestAnimationFrame(() => setInView(true));
    return () => cancelAnimationFrame(id);
  }, []);

  return (
    <div
      data-template="dusk"
      className="relative flex min-h-[100dvh] flex-1 flex-col justify-between px-10 py-12 md:px-20"
      style={{ background: "oklch(0.10 0 0)", color: "oklch(0.94 0.012 110)" }}
    >
      <div className="flex items-center gap-4">
        {hotel.logo_url ? <img src={hotel.logo_url} alt="" className="h-10 w-auto" /> : null}
        <p className="text-sm" style={{ color: "oklch(0.68 0.02 110)" }}>
          {hotel.name}
        </p>
      </div>
      <div className="max-w-[18ch]">
        <p
          className={`${NAME_CLASS} transition-[opacity,transform] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] ${
            inView ? "translate-y-0 opacity-100" : "translate-y-3 opacity-0"
          }`}
          style={{ color: "oklch(0.78 0.11 110)" }}
        >
          {guest.display_name}
        </p>
        {guest.message ? <p className="mt-6 max-w-[36ch] text-xl md:text-2xl">{guest.message}</p> : null}
      </div>
      <p className="text-sm tracking-[0.18em] uppercase" style={{ color: "oklch(0.62 0.07 230)" }}>
        {room.code}
      </p>
    </div>
  );
}
```

`src/templates/LinenWelcome.tsx`:

```tsx
import { useEffect, useState } from "react";
import { NAME_CLASS, type OccupiedProps } from "./types";

export function LinenWelcome({ hotel, room, guest }: OccupiedProps) {
  const [inView, setInView] = useState(false);
  useEffect(() => {
    const id = requestAnimationFrame(() => setInView(true));
    return () => cancelAnimationFrame(id);
  }, []);

  return (
    <div
      data-template="linen"
      className="flex min-h-[100dvh] flex-1 flex-col items-center justify-between px-10 py-12 text-center md:px-20"
      style={{ background: "oklch(0.97 0.012 85)", color: "oklch(0.28 0.035 55)" }}
    >
      <div className="flex flex-col items-center gap-4">
        {hotel.logo_url ? <img src={hotel.logo_url} alt="" className="h-10 w-auto" /> : null}
        <p className="text-sm" style={{ color: "oklch(0.48 0.02 55)" }}>
          {hotel.name}
        </p>
        <div className="h-0 w-12 border-t" style={{ borderColor: "oklch(0.82 0.03 75)" }} />
      </div>
      <div>
        <p
          className={`${NAME_CLASS} transition-opacity duration-[400ms] ${inView ? "opacity-100" : "opacity-0"}`}
          style={{ color: "oklch(0.38 0.08 45)" }}
        >
          {guest.display_name}
        </p>
        {guest.message ? (
          <p className="mt-6 max-w-[36ch] text-xl md:text-2xl" style={{ color: "oklch(0.28 0.035 55)" }}>
            {guest.message}
          </p>
        ) : null}
      </div>
      <p className="text-sm tracking-[0.18em] uppercase" style={{ color: "oklch(0.48 0.02 55)" }}>
        {room.code}
      </p>
    </div>
  );
}
```

`src/templates/HarborWelcome.tsx`:

```tsx
import { useEffect, useState } from "react";
import { NAME_CLASS, type OccupiedProps } from "./types";

export function HarborWelcome({ hotel, room, guest }: OccupiedProps) {
  const [inView, setInView] = useState(false);
  useEffect(() => {
    const id = requestAnimationFrame(() => setInView(true));
    return () => cancelAnimationFrame(id);
  }, []);

  return (
    <div
      data-template="harbor"
      className="grid min-h-[100dvh] flex-1 grid-cols-[42%_1fr]"
      style={{ background: "oklch(0.16 0.028 230)", color: "oklch(0.93 0.015 95)" }}
    >
      <div
        className={`flex flex-col justify-between px-10 py-12 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] md:px-16 ${
          inView ? "translate-x-0" : "-translate-x-4"
        }`}
        style={{ background: "oklch(0.12 0.032 230)" }}
      >
        <p className="text-sm" style={{ color: "oklch(0.72 0.03 220)" }}>
          {hotel.name}
        </p>
        <p
          className={`${NAME_CLASS} transition-opacity duration-500 ${inView ? "opacity-100" : "opacity-0"}`}
          style={{ color: "oklch(0.88 0.04 95)" }}
        >
          {guest.display_name}
        </p>
        <span />
      </div>
      <div className="flex flex-col justify-between px-10 py-12 md:px-16">
        {hotel.logo_url ? <img src={hotel.logo_url} alt="" className="h-10 w-auto" /> : <span />}
        {guest.message ? <p className="max-w-[36ch] text-xl md:text-2xl">{guest.message}</p> : <span />}
        <p className="text-sm tracking-[0.18em] uppercase" style={{ color: "oklch(0.70 0.06 200)" }}>
          {room.code}
        </p>
      </div>
    </div>
  );
}
```

`src/templates/GardenWelcome.tsx`:

```tsx
import { useEffect, useState } from "react";
import { NAME_CLASS, type OccupiedProps } from "./types";

export function GardenWelcome({ hotel, room, guest }: OccupiedProps) {
  const [inView, setInView] = useState(false);
  useEffect(() => {
    const id = requestAnimationFrame(() => setInView(true));
    return () => cancelAnimationFrame(id);
  }, []);

  return (
    <div
      data-template="garden"
      className="relative flex min-h-[100dvh] flex-1 flex-col justify-between px-10 py-12 md:px-20"
      style={{
        background: "linear-gradient(180deg, oklch(0.93 0.022 140), oklch(0.90 0.028 150))",
        color: "oklch(0.32 0.04 55)",
      }}
    >
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-center gap-4">
          {hotel.logo_url ? <img src={hotel.logo_url} alt="" className="h-10 w-auto" /> : null}
          <p className="text-sm" style={{ color: "oklch(0.45 0.03 145)" }}>
            {hotel.name}
          </p>
        </div>
        <p className="text-sm tracking-[0.18em] uppercase" style={{ color: "oklch(0.45 0.03 145)" }}>
          {room.code}
        </p>
      </div>
      <p
        className={`${NAME_CLASS} max-w-[18ch] transition-opacity duration-500 ${inView ? "opacity-100" : "opacity-0"}`}
        style={{ color: "oklch(0.34 0.07 145)" }}
      >
        {guest.display_name}
      </p>
      {guest.message ? (
        <p
          className={`max-w-[36ch] self-end text-xl transition-opacity delay-[120ms] duration-500 md:text-2xl ${
            inView ? "opacity-100" : "opacity-0"
          }`}
        >
          {guest.message}
        </p>
      ) : (
        <span />
      )}
    </div>
  );
}
```

`src/templates/StoneWelcome.tsx`:

```tsx
import { useEffect, useState } from "react";
import { NAME_CLASS, type OccupiedProps } from "./types";

export function StoneWelcome({ hotel, room, guest }: OccupiedProps) {
  const [inView, setInView] = useState(false);
  useEffect(() => {
    const id = requestAnimationFrame(() => setInView(true));
    return () => cancelAnimationFrame(id);
  }, []);

  return (
    <div
      data-template="stone"
      className="relative flex min-h-[100dvh] flex-1 flex-col px-10 py-12 md:px-20"
      style={{ background: "oklch(0.11 0.012 55)", color: "oklch(0.93 0.02 80)" }}
    >
      <div className="flex items-center gap-4">
        {hotel.logo_url ? <img src={hotel.logo_url} alt="" className="h-10 w-auto" /> : null}
        <p className="text-sm" style={{ color: "oklch(0.66 0.02 55)" }}>
          {hotel.name}
        </p>
      </div>
      <div
        className={`absolute inset-x-0 bottom-0 flex h-[32%] items-end justify-between gap-6 px-10 py-12 transition-transform duration-[600ms] ease-[cubic-bezier(0.22,1,0.36,1)] md:px-20 ${
          inView ? "translate-y-0" : "translate-y-5"
        }`}
        style={{ background: "oklch(0.15 0.016 55)" }}
      >
        <div>
          <p className={NAME_CLASS} style={{ color: "oklch(0.91 0.025 85)" }}>
            {guest.display_name}
          </p>
          {guest.message ? <p className="mt-4 max-w-[36ch] text-xl md:text-2xl">{guest.message}</p> : null}
        </div>
        <p className="text-sm tracking-[0.18em] uppercase" style={{ color: "oklch(0.72 0.08 75)" }}>
          {room.code}
        </p>
      </div>
    </div>
  );
}
```

`OccupiedWelcome.tsx`:

```tsx
import { DuskWelcome } from "./DuskWelcome";
import { GardenWelcome } from "./GardenWelcome";
import { HarborWelcome } from "./HarborWelcome";
import { LinenWelcome } from "./LinenWelcome";
import { StoneWelcome } from "./StoneWelcome";
import type { OccupiedProps } from "./types";

const SCENES = {
  dusk: DuskWelcome,
  linen: LinenWelcome,
  harbor: HarborWelcome,
  garden: GardenWelcome,
  stone: StoneWelcome,
} as const;

export function OccupiedWelcome({ templateKey, ...props }: OccupiedProps & { templateKey: string }) {
  const Scene = SCENES[templateKey as keyof typeof SCENES] ?? DuskWelcome;
  return <Scene {...props} />;
}
```

- [ ] **Step 4: WelcomeScreen router + crossfade**

`src/screens/WelcomeScreen.tsx`:

```tsx
import { useEffect, useState } from "react";
import type { ScreenData } from "../lib/api";
import { OccupiedWelcome } from "../templates/OccupiedWelcome";
import { VacantWelcome } from "../templates/VacantWelcome";

type Props = {
  screen: ScreenData;
  justPaired?: boolean;
};

export function WelcomeScreen({ screen, justPaired = false }: Props) {
  const guest = screen.guest;
  const templateKey = guest ? (screen.template?.key ?? "dusk") : null;
  const [visible, setVisible] = useState(true);
  const [shownKey, setShownKey] = useState(templateKey);

  useEffect(() => {
    if (!guest) {
      setShownKey(null);
      setVisible(true);
      return;
    }
    if (shownKey === null) {
      setShownKey(templateKey);
      setVisible(true);
      return;
    }
    if (templateKey === shownKey) return;
    setVisible(false);
    const id = window.setTimeout(() => {
      setShownKey(templateKey);
      setVisible(true);
    }, 280);
    return () => window.clearTimeout(id);
  }, [guest, templateKey, shownKey]);

  return (
    <main className="relative isolate flex min-h-[100dvh] flex-col overflow-hidden">
      {justPaired ? (
        <p className="relative z-10 bg-primary px-10 py-3 text-sm text-bg md:px-20" role="status">
          Đã ghép với phòng {screen.room.code}. Mọi TV trong phòng này hiện cùng nội dung.
        </p>
      ) : null}
      <div
        className="relative flex flex-1 flex-col transition-opacity duration-[280ms] ease-out"
        style={{ opacity: visible ? 1 : 0 }}
      >
        {guest && shownKey ? (
          <OccupiedWelcome
            templateKey={shownKey}
            hotel={screen.hotel}
            room={screen.room}
            guest={guest}
          />
        ) : (
          <VacantWelcome screen={screen} />
        )}
      </div>
    </main>
  );
}
```

- [ ] **Step 5: Verify in browser**

Player `npm run dev`. TV đã ghép phòng 101. Nhận phòng `dusk` — editorial tối. Đổi `linen` — crossfade sáng. Trả phòng — branding + ảnh nếu có. DevTools emulate `prefers-reduced-motion: reduce` — không trượt (rule global 0.01ms).

- [ ] **Step 6: Commit** (repo player)

```bash
git add src/lib/api.ts src/screens/WelcomeScreen.tsx src/templates
git commit -m "$(cat <<'EOF'
Render five occupied welcome scenes on the TV player.

Vacant rooms keep hotel branding; unknown keys fall back to dusk.
EOF
)"
```

---

## Self-review (spec coverage)

| Spec | Task |
|---|---|
| Catalog 5 keys + built-in names | 1 |
| `hotel_welcome_templates` + default dusk | 1 |
| `syncHotel` idempotent, không re-enable | 1 |
| GET receptionist enabled / manager all | 2 |
| `templates.manage` RoleSeeder | 2 |
| PATCH row, empty name → null, 403 desk | 3 |
| Không tắt default / không tắt cuối | 3 |
| PATCH default, 422 key tắt | 3 |
| Route `default` trước `{template}` | 3 |
| Check-in default + explicit key | 4 |
| 422 disabled / unknown on check-in | 4 |
| PATCH keep disabled current; reject other disabled | 4 |
| Screen `template` / null on checkout | 4 |
| Broadcast payload có key | 4 |
| CMS `/templates` + nav ẩn lễ tân | 5 |
| Thumbnail CSS, không iframe | 5 |
| Nút mặc định disabled khi tắt | 5 |
| Picker nhận phòng/đổi tên, union current | 6 |
| Room list label | 6 |
| 5 player layouts + tokens spec | 7 |
| Occupied không dùng `background_url` | 7 |
| Crossfade 280ms, dusk fallback | 7 |
| Public room cùng rule | 4+6 (stay API không phân `kind`) |
| Tenant isolation check-in | giữ test cũ Task 4 suite |

Không còn TBD/TODO trong plan. Tên method catalog `toPayload` / `isEnabled` / `syncHotel` thống nhất Task 1–4.
