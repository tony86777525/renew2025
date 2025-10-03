# 程式碼品質評分報告: Timeline API

分析並評分 URL: `/yc-championship/timeline/` (新架構) vs `/old-yc-championship/timeline/` (舊架構) 相關的程式碼品質。

---

### 1. 評分範圍

#### 新架構 (`/yc-championship/timeline/`)

*   **路由**: `Route::get('/timeline/{pref_id?}', 'timeline')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `timeline` 方法
*   **Service**:
    *   `App\Services\ChocotissueService`
        *   `getTimeline(int $page, ?int $prefId)`: ~20 行
        *   `enrichDataWithTissues($data)`: ~15 行
        *   `attachTissueData($data, string $tissueType)`: ~15 行
        *   `validatePage(int $page)`: ~5 行
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository`
        *   `getTimeline(int $limit, int $offset, ?int $prefId)`: ~20 行
    *   `App\Repositories\Chocotissue\TissueRepository`
        *   `getTissues(array $ids)`: ~5 行
        *   `getMensTissues(array $ids)`: ~5 行
*   **Model**:
    *   `App\Models\Chocolat\TissueActiveView`
    *   `App\Models\Chocolat\Tissue`
    *   `App\Models\Chocolat\MensTissue`
*   **Trait**:
    *   `App\Traits\Chocotissue\DateWindows`
    *   `App\Traits\Chocotissue\ExcludedUsers`
    *   `App\Traits\Chocotissue\CommonQueries`

#### 舊架構 (`/old-yc-championship/timeline/`)

*   **路由**: `Route::get('/timeline/{pref_id?}', 'timeline')`
*   **Controller**: `App\Http\Controllers\User\OldChocotissueController` 的 `timeline` 方法
*   **Service**:
    *   `App\Services\Old\ChocotissueService`
        *   `getCombinedTissues(...)`: ~250 行
        *   `attachDetailAttributesCombinedTissue(...)`: ~250 行
        *   其他多個超過 100 行的輔助方法 (`getList`, `existsPref`, `getTissueCommentMaster` 等)
*   **Model**:
    *   直接在 Service 中大量使用 `TissueActiveView`, `Tissue`, `NightShop`, `Cast` 等約 10-15 個 Model
*   **Trait**: 無

---

### 2. 各指標得分

| 評分指標 | 新架構 (`/yc-championship/timeline/`) | 舊架構 (`/old-yc-championship/timeline/`) |
| :--- | :---: | :---: |
| 1. 程式碼複雜度 | **18** / 20 | **2** / 20 |
| 2. 錯誤處理機制 | **16** / 20 | **4** / 20 |
| 3. 安全性 | **18** / 20 | **10** / 20 |
| 4. 程式碼可讀性 | **17** / 20 | **5** / 20 |
| 5. 架構設計 | **19** / 20 | **6** / 20 |
| **總分** | **88** / 100 | **27** / 100 |

---

### 3. 總分和等級

*   **新架構**: **88分** (B級 - 良好)
*   **舊架構**: **27分** (F級 - 嚴重問題)

---

### 4. 詳細評分解析

#### 1. 程式碼複雜度
*   **新架構 (18/20)**:
    *   **優點**: 方法行數普遍小於 50 行 (+3)，嵌套層級低 (+5)，職責分離清晰 (+3)，並透過 Repository Pattern 和 Service Layer 實現了關注點分離。`ListRepository` 負責獲取 ID 列表，`TissueRepository` 負責獲取實體，`Service` 負責編排，非常清晰。
    *   **扣分**: `ChocotissueService` 中雖然拆分了私有方法，但仍有輕微的邏輯耦合，可以再精煉 (-2)。
*   **舊架構 (2/20)**:
    *   **缺點**: `Old\ChocotissueService` 存在多個超過 200 行的「上帝方法」(God Method)，如 `getCombinedTissues` 和 `getList` (+0)。嵌套層級和 `if-else` 判斷極深 (>5)，幾乎無法維護 (+0)。所有邏輯，包括資料庫查詢、資料處理、圖片路徑拼接等全部混在一個 Service 中，嚴重違反職責分離原則 (+0)。
    *   **得分**: 僅因其存在於 Service 層而給予微薄的職責分離分數 (+2)。

#### 2. 錯誤處理機制
*   **新架構 (16/20)**:
    *   **優點**: `ChocotissueController` 和 `ChocotissueService` 中有 `try-catch` 區塊 (+4)，並使用 `\Log` 進行錯誤記錄 (+4)。對輸入參數 `page` 和 `prefId` 進行了驗證並拋出 `InvalidArgumentException`，提供了適當的錯誤回應 (+4)。
    *   **扣分**: 尚未看到統一的錯誤處理機制 (如 Laravel 的 Handler)，且錯誤訊息可以更結構化 (-4)。
*   **舊架構 (4/20)**:
    *   **缺點**: `OldChocotissueController` 中註解掉了 `try-catch` 區塊，實際執行的程式碼沒有任何錯誤處理 (+0)。`Old\ChocotissueService` 中也完全沒有 `try-catch`，任何資料庫或邏輯錯誤都會直接導致 500 錯誤頁面，且可能洩露敏感資訊 (+0)。
    *   **得分**: 僅因 Controller 中留有 `try-catch` 的殘跡，表示曾有此意圖 (+4)。

#### 3. 安全性
*   **新架構 (18/20)**:
    *   **優點**: 所有資料庫查詢都透過 Laravel Query Builder 或 Eloquent 進行，有效防禦 SQL Injection (+5)。輸入參數 `prefId` 和 `page` 在 Service 層進行了類型和範圍的驗證 (+3)。未見 `shell_exec` 等危險函數 (+4)。
    *   **扣分**: 雖然框架會處理 XSS，但未看到明確的輸出編碼或過濾，此處持保留態度 (-2)。
*   **舊架構 (10/20)**:
    *   **缺點**: `getList` 方法中大量使用 `DB::RAW` 和字串拼接來構建 SQL (`$image_sub_query`)，存在 SQL Injection 的風險 (+0)。輸入驗證缺失，直接將參數用於查詢 (+0)。
    *   **得分**: 框架層級提供了一定的 SQL Injection 和 XSS 防護 (+10)。

#### 4. 程式碼可讀性
*   **新架構 (17/20)**:
    *   **優點**: 方法命名語義化，如 `getTimeline`, `enrichDataWithTissues` (+4)。變數命名清晰，如 `$tissueIds` (+4)。邏輯結構清晰，易於追蹤 (+4)。
    *   **扣分**: 檔案和方法的註解（DocBlocks）可以更完整，描述參數和返回值 (-3)。
*   **舊架構 (5/20)**:
    *   **缺點**: 方法命名如 `getList` 過於籠統，且接收大量布林參數，形成「旗標地獄」(Flag Hell)，難以理解其確切行為 (+0)。變數命名混亂 (如 `$r`, `$v`) (+0)。巨大的 `if-else` 和 `foreach` 嵌套導致邏輯極其混亂 (+0)。大量使用魔術數字 (magic numbers) 和字串 (+0)。
    *   **得分**: 程式碼格式基本一致 (+4)，有少量註解 (+1)。

#### 5. 架構設計
*   **新架構 (19/20)**:
    *   **優點**: 完美遵循 MVC (+4)，並引入了 Service 層和 Repository Pattern (+4)。`ChocotissueService` 透過建構函式注入 `ListRepository` 和 `TissueRepository`，實現了依賴注入 (+3)。每個類別職責單一（Repository 負責資料存取，Service 負責業務邏輯），高度符合單一職責原則 (+4) 和 SOLID 原則 (+5)。
    *   **扣分**: `ChocotissueService` 承擔了所有 `yc-championship` 相關的業務，未來可能變得臃腫，可以考慮進一步拆分為更小的 Service (-1)。
*   **舊架構 (6/20)**:
    *   **缺點**: 雖然有 Controller 和 Service，但 Service 層承擔了 Repository 和部分 Controller 的職責，違反了單一職責原則 (+0)。沒有使用依賴注入，直接在方法內 `new` 物件或使用 Facade (+0)。幾乎不符合任何 SOLID 原則 (+0)。
    *   **得分**: 至少分離了 Controller 和 Service (+4)，並遵循了基礎的 MVC 架構 (+2)。

---

### 5. 新架構優化建議

新架構已經非常出色，以下是一些錦上添花的建議：

#### [高優先級]

1.  **為 Service 建立介面 (Interface)**
    *   **問題**: `ChocotissueController` 直接依賴 `ChocotissueService` 這個實體類別，降低了程式碼的可測試性和擴充性。
    *   **建議**: 建立 `ChocotissueServiceInterface`，讓 `ChocotissueService` 實作它。然後在 Controller 中依賴注入介面而非實體類。這符合 SOLID 中的依賴反轉原則（DIP），未來若要替換 Service 實作（例如，用於測試的 Mock Service），將會非常容易。

    ```php
    // app/Interfaces/Chocotissue/ChocotissueServiceInterface.php
    namespace App\Interfaces\Chocotissue;

    interface ChocotissueServiceInterface
    {
        public function getTimeline(int $page = 1, ?int $prefId = null): \Illuminate\Support\Collection;
        // ... 其他方法
    }
    ```

    ```php
    // app/Services/ChocotissueService.php
    use App\Interfaces\Chocotissue\ChocotissueServiceInterface;

    class ChocotissueService implements ChocotissueServiceInterface
    {
        // ...
    }
    ```

    ```php
    // app/Http/Controllers/User/ChocotissueController.php
    use App\Interfaces\Chocotissue\ChocotissueServiceInterface;

    public function __construct(ChocotissueServiceInterface $chocotissueService)
    {
        $this->chocotissueService = $chocotissueService;
    }
    ```

    並在 `AppServiceProvider` 中綁定介面與實作。

    ```php
    // app/Providers/AppServiceProvider.php
    public function register(): void
    {
        // ...
        $this->app->bind(
            \App\Interfaces\Chocotissue\ChocotissueServiceInterface::class,
            \App\Services\ChocotissueService::class
        );
    }
    ```

#### [中優先級]

1.  **使用 Laravel Request 進行驗證**
    *   **問題**: 目前的驗證邏輯 (`validatePage`, `validatePref`) 放在 Service 層，雖然可行，但 Laravel 提供了更優雅的 `FormRequest` 來處理 HTTP 請求的驗證。
    *   **建議**: 建立一個 `TimelineRequest`，將驗證規則和授權邏輯從 Service 和 Controller 中分離出來。

    ```bash
    php artisan make:request Chocotissue/TimelineRequest
    ```

    ```php
    // app/Http/Requests/Chocotissue/TimelineRequest.php
    namespace App\Http\Requests\Chocotissue;

    use Illuminate\Foundation\Http\FormRequest;

    class TimelineRequest extends FormRequest
    {
        public function authorize(): bool
        {
            return true; // Or add authorization logic
        }

        public function rules(): array
        {
            return [
                'page' => ['sometimes', 'integer', 'min:1'],
                // pref_id is from route parameter, but can be validated here too if needed
            ];
        }
    }
    ```

    ```php
    // app/Http/Controllers/User/ChocotissueController.php
    use App\Http\Requests\Chocotissue\TimelineRequest;

    public function timeline(TimelineRequest $request, ?int $prefId = null)
    {
        try {
            $validated = $request->validated();
            $page = $validated['page'] ?? 1;

            $data = $this->chocotissueService->getTimeline($page, $prefId);

            return view('user.chocotissue.timeline', compact('data'));
        } catch (Exception $e) {
            // ... error handling
        }
    }
    ```

2.  **引入資料傳輸物件 (DTO)**
    *   **問題**: `ListRepository` 返回的 `stdClass` 集合和 `Service` 最終返回的 `Collection` 雖然包含 Eloquent Model，但結構不夠明確，依賴於資料庫欄位名稱。
    *   **建議**: 建立一個 `TimelineItemDTO`，用於在各層之間傳遞結構化的資料。這能讓程式碼更具可讀性，且與底層資料庫解耦。

    ```php
    // app/DTOs/TimelineItemDTO.php
    namespace App\DTOs;

    use App\Models\Chocolat\Tissue;
    use App\Models\Chocolat\MensTissue;

    class TimelineItemDTO
    {
        public function __construct(
            public readonly int $tissueId,
            public readonly string $tissueType,
            public readonly string $releaseDate,
            public readonly ?Tissue|?MensTissue $tissue,
            // ... 其他需要的屬性
        ) {}
    }
    ```

    在 Service 層中，將查詢結果轉換為 DTO 集合再返回給 Controller。

#### [低優先級]

1.  **增加快取機制**
    *   **問題**: `timeline` 是一個高頻訪問的 API，每次都查詢資料庫會造成不必要的負擔。
    *   **建議**: 在 `ChocotissueService` 中引入快取。可以根據 `page` 和 `prefId` 生成唯一的快取鍵，並將 `ListRepository` 的查詢結果快取一小段時間（如 1-5 分鐘）。

    ```php
    // app/Services/ChocotissueService.php
    use Illuminate\Support\Facades\Cache;

    public function getTimeline(int $page = 1, ?int $prefId = null): \Illuminate\Support\Collection
    {
        $this->validatePage($page);

        $limit = 30;
        $offset = ($page - 1) * $limit;

        $cacheKey = "chocotissue.timeline.page.{$page}.pref." . ($prefId ?? 'all');

        // 先從快取讀取，快取 1 分鐘
        $data = Cache::remember($cacheKey, now()->addMinutes(1), function () use ($limit, $offset, $prefId) {
            return $this->listRepository->getTimeline($limit, $offset, $prefId);
        });

        if ($data->isEmpty()) {
            return collect([]);
        }

        return $this->enrichDataWithTissues($data);
    }
    ```

    *注意*: `enrichDataWithTissues` 中的 Eloquent 查詢也應考慮是否需要快取，可以使用 `remember` 方法鏈接在 Eloquent 查詢上，或在 Repository 層實現。