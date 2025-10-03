# 程式碼品質評分報告: Detail API

**分析對象**: `/yc-championship/detail/{tissue_id}` (新架構)

**評分日期**: 2024-07-26

**摘要**: 本次評分專注於投稿詳情頁 (`/detail`) 功能的相關程式碼。此功能透過 `DetailService` 呼叫 `ListRepository::getTissues` 方法來獲取單一投稿的資料。雖然這種方式能夠正確運作，但 `getTissues` 方法是為獲取列表而設計的，其內部查詢對於僅獲取單一投稿的場景來說過於複雜，存在效能優化的空間。整體程式碼品質依然維持在較高水準。

---

### 1. 評分範圍

*   **路由**: `Route::get('/detail/{tissue_id}', 'detail')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `detail` 方法。
*   **Service**: `App\Services\Chocotissue\DetailService.php`
    *   `getDetailData(int $tissueId)`: ~25 行
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository.php`
        *   `getTissues(...)`: ~60 行 (被 `DetailService` 重用)
*   **Trait**:
    *   `App\Traits\Chocotissue\TissueEnricher.php`
    *   `App\Traits\Chocotissue\CommonQueries.php`
    *   `App\Traits\Chocotissue\DateWindows.php`
    *   `App\Traits\Chocotissue\ExcludedUsers.php`

---

### 2. 各指標得分

| 評分指標 | `/yc-championship/detail/{tissue_id}` (新架構) |
| :--- | :---: |
| 1. 程式碼複雜度 | **18** / 20 |
| 2. 錯誤處理機制 | **16** / 20 |
| 3. 安全性 | **19** / 20 |
| 4. 程式碼可讀性 | **19** / 20 |
| 5. 架構設計 | **18** / 20 |
| **總分** | **90** / 100 |

---

### 3. 總分和等級

*   **新架構 (`/detail/{tissue_id}/`)**: **90分** (A級 - 優秀)

---

### 4. 詳細評分解析

*   **程式碼複雜度 (18/20)**:
    *   **優點**: `DetailService` 極為簡潔，職責清晰 (+5)。
    *   **扣分**: `ListRepository::getTissues` 方法雖然被重用，但其內部包含了多個 `leftJoin` 和一個複雜的子查詢 (`buildUserTissueQuery`)，對於獲取單一 `tissue` 的任務來說，這是不必要的複雜度 (-2)。

*   **錯誤處理機制 (16/20)**:
    *   **優點**: Controller 層具備完整的 `try-catch` 區塊，能捕捉業務邏輯錯誤和系統錯誤 (+4)。Service 層也對 `tissueId` 進行了存在性驗證 (+4)。
    *   **扣分**: 與之前的評分相同，缺乏一個應用程式級別的統一例外處理中心 (-4)。

*   **安全性 (19/20)**:
    *   **優點**: 所有查詢都透過 Query Builder 進行，並正確使用了參數綁定，有效防禦 SQL Injection (+5)。已移除 `env()` 的直接呼叫，增強了設定檔的穩定性 (+5)。
    *   **扣分**: 雖然沒有直接的漏洞，但 `getTissues` 方法中存在一個 `DB::raw("'{$tissueType}' AS tissue_type")` 的寫法。雖然 `$tissueType` 是內部定義的常數，但直接內插變數到 SQL 字串中總是被視為一個潛在的風險點 (-1)。

*   **程式碼可讀性 (19/20)**:
    *   **優點**: 類別和方法的命名（如 `DetailService`, `getDetailData`）都非常清晰地表達了它們的意圖 (+5)。
    *   **扣分**: 當開發者追蹤到 `DetailService` 呼叫 `getTissues` 時，可能會感到困惑，因為 `getTissues` 的命名和其內部複雜的查詢似乎是為列表頁面設計的，而不是為獲取單一項目 (-1)。

*   **架構設計 (18/20)**:
    *   **優點**: 依然遵循了 Controller -> Service -> Repository 的分層結構 (+4)。
    *   **扣分**: 為了獲取單一 `tissue` 而重用一個複雜的列表查詢方法，雖然是程式碼複用，但卻不是一個高效或職責清晰的設計。這導致了不必要的資料庫負載。一個更理想的架構應該有一個專門的、輕量級的方法來獲取單一實體 (-2)。

---

### 5. 新架構優化建議

此功能的核心優化點在於為「獲取單一投稿」這個明確的任務，建立一個專屬且高效的資料庫查詢路徑。

#### [高優先級]

1.  **在 `ListRepository` 中建立專用的 `findTissueById` 方法**
    *   **問題**: `DetailService` 呼叫 `getTissues` 來獲取單一投稿，這會觸發一個包含多個 `JOIN` 和複雜子查詢的重量級查詢，造成不必要的效能浪費。
    *   **建議**: 在 `ListRepository` 中建立一個新的、輕量級的方法，名為 `findTissueById`。這個方法應該只執行一個非常簡單的查詢，直接從 `tissue_active_view` 或 `tissues` 表中根據 ID 獲取資料，而不需要執行 `buildUserTissueQuery` 等複雜邏輯。

    **步驟 1: 在 `ListRepository.php` 中新增 `findTissueById` 方法**
    
