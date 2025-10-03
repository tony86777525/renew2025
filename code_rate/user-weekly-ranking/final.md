# 程式碼品質評分報告: User Weekly Rankings API

**分析對象**: `/yc-championship/user-weekly-rankings/` (新架構)

**評分日期**: 2024-07-26

**摘要**: 本次評分專注於使用者**週**排名 (`user-weekly-rankings`) 功能的相關程式碼。其架構與使用者總排名 (`user-rankings`) 高度相似，同樣採用了 Controller -> Service -> Repository -> QueryBuilder 的清晰分層設計。核心的複雜 SQL 邏輯被有效地封裝在 `UserScoreQueryBuilder` 中，整體程式碼品質維持在卓越水準。

---

### 1. 評分範圍

*   **路由**: `Route::get('/user-weekly-rankings/{pref_id?}', 'userWeeklyRankings')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `userWeeklyRankings` 方法。
*   **Service**: `App\Services\Chocotissue\UserWeeklyRankingService.php`
    *   `getUserWeeklyRankingData(int $page, ?int $prefId)`: ~25 行
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository.php`
        *   `getUserWeeklyRankings(int $limit, int $offset, ?int $prefId)`: ~45 行
    *   `App\Repositories\Chocotissue\TissueRepository.php`
        *   `getUserRankingTopTissueOfUsers(...)`: (與總排名共用)
*   **Trait**:
    *   `App\Traits\Chocotissue\TissueEnricher.php`
    *   `App\Traits\Chocotissue\CommonQueries.php`
    *   `App\Traits\Chocotissue\DateWindows.php`
    *   `App\Traits\Chocotissue\ExcludedUsers.php`
*   **QueryBuilder**:
    *   `App\QueryBuilders\Chocotissue\UserScoreQueryBuilder.php`
        *   `buildWeeklyScoreQueryBuild(...)`: ~55 行
    *   `App\QueryBuilders\Chocotissue\UserTopTissueQueryBuilder.php`: (與總排名共用)

---

### 2. 各指標得分

| 評分指標 | `/yc-championship/user-weekly-rankings/` (新架構) |
| :--- | :---: |
| 1. 程式碼複雜度 | **20** / 20 |
| 2. 錯誤處理機制 | **16** / 20 |
| 3. 安全性 | **18** / 20 |
| 4. 程式碼可讀性 | **20** / 20 |
| 5. 架構設計 | **20** / 20 |
| **總分** | **94** / 100 |

---

### 3. 總分和等級

*   **新架構 (`/user-weekly-rankings/`)**: **94分** (A+級 - 卓越)

---

### 4. 詳細評分解析

*   **程式碼複雜度 (20/20)**:
    *   **優點**: 完美地遵循了關注點分離原則。`UserWeeklyRankingService` 極度輕量。`ListRepository` 扮演了清晰的協調者角色。所有複雜的計分和分組邏輯都被封裝在 `UserScoreQueryBuilder::buildWeeklyScoreQueryBuild` 方法中，並且該方法內部透過提取 `getCanonicalUserGroupingExpression` 保持了低複雜度。

*   **錯誤處理機制 (16/20)**:
    *   **優點**: Controller 層具備了完整的 `try-catch` 區塊，能夠區分業務邏輯錯誤 (`InvalidArgumentException`) 和系統錯誤 (`Exception`)，並回傳不同的 HTTP 狀態碼。
    *   **扣分**: 與先前的評分一致，應用程式層級缺乏一個統一的例外處理中心 (Laravel's Exception Handler)，導致 Controller 中存在重複的 `catch` 樣板程式碼。

*   **安全性 (18/20)**:
    *   **優點**: 透過 Eloquent 和 Query Builder 有效地防禦了 SQL Injection。移除了 `env()` 的直接呼叫，增強了設定檔的穩定性和安全性。
    *   **扣分**: 在 `UserScoreQueryBuilder::buildWeeklyScoreQueryBuild` 方法中，`$weekStartDate` 和 `$snsWeekStartDate` 兩個日期變數仍是直接內插到 `DB::raw()` 的 SQL 字串中。雖然 `Carbon` 物件目前是安全的，但這並非最安全的實踐。**堅持使用參數綁定是達到滿分的最後一哩路**。

*   **程式碼可讀性 (20/20)**:
    *   **優點**: 命名清晰，職責明確。`UserScoreQueryBuilder` 中對 `GROUP BY` 邏輯的提取和常數的使用，使得這段最複雜的程式碼變得非常容易理解。`DateWindows` Trait 的使用也讓 `weekStartDate()`、`snsWeekStartDate()` 等日期的定義和意圖一目了然。

*   **架構設計 (20/20)**:
    *   **優點**: 堪稱典範的 Laravel 分層架構。從 Controller 到 Service，再到 Repository，最後到專門處理複雜查詢的 QueryBuilder，每一層的職責都非常單一且明確。這種設計不僅易於維護，也極大地提升了程式碼的可測試性。

---

### 5. 新架構優化建議

您的程式碼品質已經非常出色。以下建議旨在追求極致的完美與安全性。

#### [高優先級]

1.  **在 `buildWeeklyScoreQueryBuild` 中使用參數綁定**
    *   **問題**: 這是目前程式碼中唯一明顯的安全隱患點。直接將變數內插到 SQL 字串中，即使來源可靠，也應視為不良實踐。
    *   **建議**: 將 `DB::raw()` 中的日期字串改為 `?` 佔位符，並將變數作為綁定參數傳入。

    
