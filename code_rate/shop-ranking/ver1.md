# 程式碼品質評分報告: Shop Rankings API

**分析對象**: `/yc-championship/shop-rankings/` (新架構)

**評分日期**: 2024-07-26

**摘要**: 本次評分專注於店鋪排名 (`shop-rankings`) 功能的相關程式碼。這是目前為止分析過最複雜的功能，涉及多個資料庫的店鋪實體合併、分數計算與排名。目前的架構透過 `ShopRankingQueryBuilder` 成功地將這種極高的複雜性封裝起來，保持了上層程式碼（Service, Repository）的清晰度。整體品質依然維持在非常高的水準。

---

### 1. 評分範圍

*   **路由**: `Route::get('/shop-rankings/{pref_id?}', 'shopRankings')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `shopRankings` 方法。
*   **Service**: `App\Services\Chocotissue\ShopRankingService.php`
    *   `getShopRankingData(...)`: ~60 行 (包含過濾邏輯)
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository.php`
        *   `getShopRankings(...)`: ~40 行
    *   `App\Repositories\Chocotissue\TissueRepository.php`
        *   `getShopRankingTopTissueOfUsers(...)`: ~40 行
*   **Trait**:
    *   `App\Traits\Chocotissue\TissueEnricher.php`
    *   `App\Traits\Chocotissue\CommonQueries.php`
    *   `App\Traits\Chocotissue\DateWindows.php`
*   **QueryBuilder**:
    *   `App\QueryBuilders\Chocotissue\ShopRankingQueryBuilder.php`
        *   `buildEligibleTissue(...)`: ~70 行
        *   `buildTissueChocoShop(...)`: ~30 行
        *   `buildTissueNightShop(...)`: ~30 行
        *   `buildUnpivoted(...)`: ~1 行
        *   `buildUniqueCastShopPoints(...)`: ~15 行

---

### 2. 各指標得分

| 評分指標 | `/yc-championship/shop-rankings/` (新架構) |
| :--- | :---: |
| 1. 程式碼複雜度 | **19** / 20 |
| 2. 錯誤處理機制 | **16** / 20 |
| 3. 安全性 | **19** / 20 |
| 4. 程式碼可讀性 | **18** / 20 |
| 5. 架構設計 | **20** / 20 |
| **總分** | **92** / 100 |

---

### 3. 總分和等級

*   **新架構 (`/shop-rankings/`)**: **92分** (A級 - 優秀)

---

### 4. 詳細評分解析

*   **程式碼複雜度 (19/20)**:
    *   **優點**: 再次完美地展示了分層架構的威力。`ShopRankingService` 和 `ListRepository` 的邏輯都非常簡單，它們的核心職責就是呼叫和組裝 QueryBuilder (+5)。`ShopRankingQueryBuilder` 承擔了所有的複雜性，其內部又透過多個 `build...` 方法將一個巨大的查詢拆分成多個可管理的步驟，例如 `buildEligibleTissue`, `buildTissueChocoShop`, `buildUnpivoted` 等，這是一個非常好的實踐 (+5)。
    *   **扣分**: `ShopRankingQueryBuilder::buildEligibleTissue` 方法是整個流程的核心，行數約 70 行，包含了多個 `leftJoin` 和一個複雜的 `GROUP BY` 子查詢，是整個流程中最難理解的部分 (-1)。

*   **錯誤處理機制 (16/20)**:
    *   **優點**: Controller 層的 `try-catch` 區塊提供了可靠的錯誤捕捉和適當的 HTTP 回應 (+4)。Service 層也透過拋出 `InvalidArgumentException` 進行了輸入驗證 (+4)。
    *   **扣分**: 與之前的評分相同，缺乏一個應用程式級別的統一例外處理中心，導致 Controller 中存在重複的 `catch` 樣板程式碼 (-4)。

*   **安全性 (19/20)**:
    *   **優點**: 所有查詢都透過 Query Builder 進行，有效防禦 SQL Injection (+5)。已移除 `env()` 的直接呼叫，增強了設定檔的穩定性 (+5)。
    *   **扣分**: 雖然沒有直接的漏洞，但 `ShopRankingService` 中的過濾邏輯是在從資料庫獲取**所有**排名資料後，在記憶體中進行的 (`->filter()`, `->slice()`)。如果排名資料非常龐大，這可能會有效能問題，甚至有被 DoS 攻擊的風險（透過不斷請求不同頁面耗盡伺服器記憶體）。更安全的做法是將過濾條件（如 `prefId`）盡可能地推到資料庫層級執行 (-1)。

*   **程式碼可讀性 (18/20)**:
    *   **優點**: 類別和方法的命名（如 `ShopRankingQueryBuilder`, `buildUnpivoted`）都非常清晰地表達了它們的意圖 (+5)。`CommonQueries` 和 `DateWindows` 等 Trait 的使用也讓程式碼更易於理解 (+4)。
    *   **扣分**: `ShopRankingQueryBuilder` 中的 `buildEligibleTissue` 方法包含了大量的 `leftJoin` 和 `COALESCE`，對於不熟悉資料庫結構的開發者來說，需要花費較多時間來理解其背後的邏輯 (-2)。

*   **架構設計 (20/20)**:
    *   **優點**: 這是此功能的亮點。面對極其複雜的業務規則（合併兩種店鋪、計算分數、處理綁定關係），此架構依然保持了極高的清晰度和擴充性。QueryBuilder 模式在這裡發揮了至關重要的作用，它將「如何建構查詢」的細節與「何時執行查詢」的業務邏輯完全解耦，是教科書級別的應用 (+5)。

---

### 5. 新架構優化建議

您的架構已經非常穩固，以下建議主要集中在效能和可讀性的微調上。

#### [高優先級]

1.  **將過濾邏輯下推至資料庫層**
    *   **問題**: `ShopRankingService::getShopRankingData` 方法先從 `ListRepository` 獲取了**所有**店鋪的排名，然後在 Service 層的記憶體中對 `prefId` 進行過濾，並手動進行分頁 (`slice`)。當店鋪數量很大時，這會造成巨大的記憶體和 CPU 浪費。
    *   **建議**: 修改 `ListRepository::getShopRankings` 方法，使其能夠接收 `$prefId`, `$limit`, `$offset` 等參數，並將這些過濾和分頁條件直接應用在資料庫查詢中。

    
