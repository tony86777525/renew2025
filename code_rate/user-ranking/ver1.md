# 程式碼品質評分報告: User Rankings API

**分析對象**: `/yc-championship/user-rankings/` (新架構)

**評分日期**: 2024-07-26

**摘要**: 本次評分專注於使用者排名 (`user-rankings`) 功能的相關程式碼。整體架構與 `timeline` 相似，同樣遵循了良好的分層設計，將複雜的 SQL 查詢封裝在 Repository 和 QueryBuilder 中，Service 層則負責業務邏輯的編排。程式碼品質維持在相當高的水準。

---

### 1. 評分範圍

*   **路由**: `Route::get('/user-rankings/{pref_id?}', 'userRankings')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `userRankings` 方法。
*   **Service**:
    *   `App\Services\Chocotissue\UserRankingService.php`
        *   `getUserRankingData(int $page, ?int $prefId)`: ~25 行
        *   `validatePage(int $page)`, `validatePref(?int $prefId)`: ~5 行/個
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository.php`
        *   `getUserRankings(int $limit, int $offset, ?int $prefId)`: ~40 行
    *   `App\Repositories\Chocotissue\TissueRepository.php`
        *   `getUserRankingTopTissueOfUsers(...)`: ~40 行
*   **Trait**:
    *   `App\Traits\Chocotissue\TissueEnricher.php` (由 `UserRankingService` 使用)
    *   `App\Traits\Chocotissue\CommonQueries.php` (由 `ListRepository` 使用)
    *   `App\Traits\Chocotissue\DateWindows.php` (由 `ListRepository` 使用)
    *   `App\Traits\Chocotissue\ExcludedUsers.php` (由 `ListRepository` 使用)
*   **QueryBuilder**:
    *   `App\QueryBuilders\Chocotissue\UserScoreQueryBuilder.php`
        *   `buildTissueQueryBuild(...)`: ~25 行
        *   `buildScoreQueryBuild(...)`: ~50 行
    *   `App\QueryBuilders\Chocotissue\UserTopTissueQueryBuilder.php`
        *   `buildUserTissueQuery(...)`: ~50 行
        *   `buildUserRankingOrderTissueQuery(...)`: ~20 行

---

### 2. 各指標得分

| 評分指標 | `/yc-championship/user-rankings/` (新架構) |
| :--- | :---: |
| 1. 程式碼複雜度 | **19** / 20 |
| 2. 錯誤處理機制 | **16** / 20 |
| 3. 安全性 | **18** / 20 |
| 4. 程式碼可讀性 | **18** / 20 |
| 5. 架構設計 | **20** / 20 |
| **總分** | **91** / 100 |

---

### 3. 總分和等級

*   **新架構 (`/user-rankings/`)**: **91分** (A級 - 優秀)

---

### 4. 詳細評分解析

*   **程式碼複雜度 (19/20)**:
    *   **優點**: 職責分離非常出色。`UserRankingService` 極為簡潔 (+5)。`ListRepository` 雖然行數稍多，但其職責是組裝 QueryBuilder，本身邏輯不複雜 (+3)。真正的複雜度被完美地封裝在 `UserScoreQueryBuilder` 中，這正是 QueryBuilder 模式的價值所在 (+5)。
    *   **扣分**: `UserScoreQueryBuilder::buildScoreQueryBuild` 方法行數約 50 行，且包含多個 `leftJoinSub`，是整個流程中最複雜的部分，可讀性稍有挑戰 (-1)。

*   **錯誤處理機制 (16/20)**:
    *   **優點**: Controller 層有完整的 `try-catch` 區塊，能捕捉 `InvalidArgumentException` 並回傳 400，其他 Exception 則記錄日誌並回傳 500 (+4)。Service 層也透過拋出 `InvalidArgumentException` 進行了輸入驗證 (+4)。
    *   **扣分**: 與上次評分相同，缺乏統一的錯誤處理機制 (如 Laravel Exception Handler)，且日誌記錄可以更結構化 (-4)。

*   **安全性 (18/20)**:
    *   **優點**: 所有資料庫查詢都透過 Laravel Query Builder 進行，有效防禦 SQL Injection (+5)。`DB::raw` 的使用被限制在安全的範圍內（如 `MAX()`, `CONCAT()`），沒有拼接外部輸入 (+4)。輸入參數 `prefId` 在 Service 層進行了驗證 (+3)。
    *   **扣分**: 未看到明確的輸出編碼 (XSS 防護)，此處持保留態度 (-2)。

*   **程式碼可讀性 (18/20)**:
    *   **優點**: 類別和方法命名清晰，如 `UserRankingService`, `UserScoreQueryBuilder` (+4)。`CommonQueries` 和 `DateWindows` 等 Trait 的使用，讓 Repository 的意圖更清晰 (+4)。
    *   **扣分**: `UserScoreQueryBuilder` 中的 `GROUP BY CASE WHEN ...` 語句雖然功能強大，但對於初見此程式碼的開發者來說需要花費較多時間理解其分組邏輯 (-2)。

*   **架構設計 (20/20)**:
    *   **優點**: 這是此架構最強大的部分。完美地展示了 Controller -> Service -> Repository -> QueryBuilder 的分層架構 (+5)。`UserScoreQueryBuilder` 的引入是關鍵，它將複雜的 SQL 組裝邏輯從 Repository 中抽離，使 Repository 只需負責「呼叫」和「組裝」查詢，職責更加單一 (+5)。完全符合 SOLID 原則 (+5)。

---

### 5. 新架構優化建議

這次的架構已經非常成熟和專業，以下是一些可以讓它更上一層樓的建議：

#### [高優先級]

1.  **在 QueryBuilder 中使用常數取代魔術字串**
    *   **問題**: 在 `UserScoreQueryBuilder` 的 `GROUP BY` 子句中，使用了如 `'choco_cast_'`, `'night_cast_'` 等「魔術字串 (Magic Strings)」。這些字串如果未來需要修改，必須在多處手動變更，容易出錯。
    *   **建議**: 在 `UserScoreQueryBuilder` 或相關的 Model 中定義這些前綴為常數，並在查詢中引用它們。
