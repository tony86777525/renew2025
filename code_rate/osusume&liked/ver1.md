# 程式碼品質評分報告: Recommendations & Liked API

**分析對象**: `/yc-championship/{pref_id?}` (新架構)

**評分日期**: 2024-07-26

**摘要**: 本次評分專注於推薦 (`recommendations`) 與按讚列表 (`likedTissues`) 功能的相關程式碼。此路由的兩個分支均遵循了良好的分層架構，將資料庫查詢邏輯有效地封裝在 Repository 層。然而，兩個分支的 Service 層都存在一些可以優化的問題：`RecommendationService` 的職責過於單薄，而 `LikedService` 則重用了為列表設計的複雜查詢方法來獲取資料，存在效能優化的空間。整體程式碼品質維持在較高水準。

---

### 1. 評分範圍

*   **路由**: `Route::get('/{pref_id?}', 'handle')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `handle`, `recommendations`, `likedTissues` 方法。
*   **Service**:
    *   `App\Services\Chocotissue\RecommendationService.php`
        *   `getRecommendationData(...)`: ~30 行
    *   `App\Services\Chocotissue\LikedService.php`
        *   `getLikedData(...)`: ~30 行
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository.php`
        *   `getPcRecommendations(...)`: ~40 行
        *   `getSpRecommendations(...)`: ~40 行
        *   `getTissues(...)`: ~60 行 (被 `LikedService` 重用)
*   **Trait**:
    *   `App\Traits\Chocotissue\TissueEnricher.php`
    *   `App\Traits\Chocotissue\CommonQueries.php`
    *   `App\Traits\Chocotissue\DateWindows.php`

---

### 2. 各指標得分

| 評分指標 | `/yc-championship/{pref_id?}` (新架構) |
| :--- | :---: |
| 1. 程式碼複雜度 | **18** / 20 |
| 2. 錯誤處理機制 | **16** / 20 |
| 3. 安全性 | **19** / 20 |
| 4. 程式碼可讀性 | **19** / 20 |
| 5. 架構設計 | **18** / 20 |
| **總分** | **90** / 100 |

---

### 3. 總分和等級

*   **新架構 (`/yc-championship/{pref_id?}`)**: **90分** (A級 - 優秀)

---

### 4. 詳細評分解析

*   **程式碼複雜度 (18/20)**:
    *   **優點**: Controller 和 Service 層的邏輯都非常簡潔，職責清晰 (+5)。Repository 層的方法雖然行數稍多，但其內部邏輯是線性的，主要是組裝和執行查詢，沒有過深的嵌套 (+3)。
    *   **扣分**: `LikedService` 重用了 `ListRepository::getTissues` 方法。該方法內部包含了複雜的子查詢，對於僅根據 ID 列表獲取投稿的場景來說，這是不必要的複雜度 (-2)。

*   **錯誤處理機制 (16/20)**:
    *   **優點**: Controller 層具備完整的 `try-catch` 區塊，能捕捉業務邏輯錯誤和系統錯誤 (+4)。Service 層也對輸入參數進行了驗證 (+4)。
    *   **扣分**: 與之前的評分相同，缺乏一個應用程式級別的統一例外處理中心 (-4)。

*   **安全性 (19/20)**:
    *   **優點**: 所有查詢都透過 Query Builder 進行，並正確使用了參數綁定，有效防禦 SQL Injection (+5)。已移除 `env()` 的直接呼叫，增強了設定檔的穩定性 (+5)。
    *   **扣分**: `getTissues` 方法中存在一個 `DB::raw("'{$tissueType}' AS tissue_type")` 的寫法。雖然 `$tissueType` 是內部定義的常數，但直接內插變數到 SQL 字串中總是被視為一個潛在的風險點 (-1)。

*   **程式碼可讀性 (19/20)**:
    *   **優點**: 類別和方法的命名（如 `RecommendationService`, `getLikedData`）都非常清晰地表達了它們的意圖 (+5)。
    *   **扣分**: 當開發者追蹤到 `LikedService` 呼叫 `getTissues` 時，可能會感到困惑，因為 `getTissues` 的命名和其內部複雜的查詢似乎是為列表頁面設計的，而不是為獲取按讚列表 (-1)。

*   **架構設計 (18/20)**:
    *   **優點**: 依然遵循了 Controller -> Service -> Repository 的分層結構 (+4)。
    *   **扣分**:
        *   `RecommendationService` 的職責過於單薄，它幾乎只是一個將參數從 Controller 傳遞到 Repository 的「傳話筒」，可以考慮將其邏輯合併到 Controller 或其他相關 Service 中 (-1)。
        *   `LikedService` 為了獲取按讚列表而重用一個複雜的列表查詢方法，雖然是程式碼複用，但卻不是一個高效或職責清晰的設計。一個更理想的架構應該有一個專門的、輕量級的方法來根據 ID 獲取投稿 (-1)。

---

### 5. 新架構優化建議

此路由的核心優化點在於簡化 Service 層的職責，並為「按讚列表」這個明確的任務，建立一個專屬且高效的資料庫查詢路徑。

#### [高優先級]

1.  **為 `LikedService` 建立專用的 `findTissuesByIds` 方法**
    *   **問題**: `LikedService` 呼叫 `getTissues` 來獲取按讚列表，這會觸發一個包含多個 `JOIN` 和複雜子查詢的重量級查詢，造成不必要的效能浪費。
    *   **建議**: 在 `ListRepository` 中建立一個新的、輕量級的方法，名為 `findTissuesByIds`。這個方法應該只執行一個非常簡單的查詢，直接從 `tissue_active_view` 視圖中根據 ID 列表獲取資料，而不需要執行 `buildUserTissueQuery` 等複雜邏輯。
    
