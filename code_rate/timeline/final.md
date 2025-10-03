# 程式碼品質評分報告 (第二次更新)

**分析對象**: `/yc-championship/timeline/` (新架構) vs `/old-yc-championship/timeline/` (舊架構)

**評分日期**: 2024-07-26

**摘要**: 本次評分是在新架構將單一的 `ChocotissueService` 拆分為多個職責單一的 Service (如 `TimelineService`, `RankingService` 等) 之後進行的。這次重構顯著提升了程式碼品質，特別是在架構設計和複雜度方面，使分數從 **88 (B級)** 提升至 **93 (A級)**。

---

### 1. 評分範圍 (更新後)

#### 新架構 (`/yc-championship/timeline/`)

*   **路由**: `Route::get('/timeline/{pref_id?}', 'timeline')`
*   **Controller**: `App\Http\Controllers\User\ChocotissueController` 的 `timeline` 方法 (註: 此 Controller 現在注入了多個 Service，成為下一個優化點)。
*   **Service**:
    *   `App\Services\Chocotissue\TimelineService.php`
        *   `getTimelineData(int $page, ?int $prefId)`: ~25 行
        *   `validatePage(int $page)`, `validatePref(?int $prefId)`: ~5 行/個
*   **Repository**:
    *   `App\Repositories\Chocotissue\ListRepository`
        *   `getTimeline(int $limit, int $offset, ?int $prefId)`: ~20 行
    *   `App\Repositories\Chocotissue\TissueRepository`
*   **Trait**:
    *   `App\Traits\Chocotissue\TissueEnricher.php` (由 `TimelineService` 使用)
    *   `App\Traits\Chocotissue\CommonQueries.php` (由 `ListRepository` 使用)
    *   `App\Traits\Chocotissue\DateWindows.php` (由 `ListRepository` 使用)
*   **QueryBuilder**:
    *   `ListRepository` 中使用的 Laravel Query Builder。

#### 舊架構 (`/old-yc-championship/timeline/`)

*   **路由**: `Route::get('/timeline/{pref_id?}', 'timeline')`
*   **Controller**: `App\Http\Controllers\User\OldChocotissueController` 的 `timeline` 方法。
*   **Service**:
    *   `App\Services\Old\ChocotissueService.php`
        *   `getCombinedTissues(...)`: ~250 行
        *   `attachDetailAttributesCombinedTissue(...)`: ~250 行
        *   其他多個超過 100 行的輔助方法。
*   **Model**: 直接在 Service 中大量使用約 10-15 個 Model。
*   **Trait / Repository / QueryBuilder**: 無。

---

### 2. 各指標得分 (更新後)

| 評分指標 | 新架構 (`/yc-championship/timeline/`) | 舊架構 (`/old-yc-championship/timeline/`) |
| :--- | :---: | :---: |
| 1. 程式碼複雜度 | **20** / 20 | **2** / 20 |
| 2. 錯誤處理機制 | **16** / 20 | **4** / 20 |
| 3. 安全性 | **18** / 20 | **10** / 20 |
| 4. 程式碼可讀性 | **19** / 20 | **5** / 20 |
| 5. 架構設計 | **20** / 20 | **6** / 20 |
| **總分** | **93** / 100 | **27** / 100 |

---

### 3. 總分和等級 (更新後)

*   **新架構**: **93分** (A級 - 優秀)
*   **舊架構**: **27分** (F級 - 嚴重問題)

---

### 4. 詳細評分解析 (新架構分數提升原因)

*   **程式碼複雜度 (18 -> 20)**:
    *   **提升原因**: `TimelineService` 現在只專注於時間軸邏輯，方法行數極少，職責非常單一。複雜的資料庫查詢被完全隔離在 `ListRepository` 中，業務邏輯與資料存取完美分離，達到了理想狀態。

*   **程式碼可讀性 (17 -> 19)**:
    *   **提升原因**: 類別名稱 `TimelineService` 直接反映其功能，開發者可以立即明白它的用途，不再需要進入一個巨大的 `ChocotissueService` 中尋找 `getTimeline` 方法。通用邏輯被抽取到 `TissueEnricher` Trait，意圖更加清晰。

*   **架構設計 (19 -> 20)**:
    *   **提升原因**: 透過將一個巨大的 Service 拆分為多個按功能劃分的小型 Service，您的架構現在更深入地實踐了**單一職責原則 (SRP)**。每個 Service 都有一個非常明確的變更理由，大大提高了可維護性，達到了此指標的滿分標準。

---

### 5. 新架構優化建議 (更新後)

您已經解決了「肥服務 (Fat Service)」的問題，但這也引出了一個新的、更進階的架構問題：「肥控制器 (Fat Controller)」。`ChocotissueController` 現在注入了 10 個不同的 Service，這是一個明確的訊號，表示它承擔了太多職責。

以下是針對這個新問題的優化建議：

#### [高優先級]

1.  **拆分控制器 (Splitting the Controller)**
    *   **問題**: `ChocotissueController` 現在是一個協調中心，負責處理時間軸、排名、Hashtag、詳情頁等多種完全不同的請求。它的建構函式變得非常臃腫，違反了單一職責原則。
    *   **建議**: 根據 `routes/web.php` 中的功能分組，將 `ChocotissueController` 拆分成多個更專注的控制器。

#### [中優先級]

1.  **整合驗證邏輯到 Trait**

    *   **問題**: `validatePage` 和 `validatePref` 這兩個方法在多個新的 Service 中（如 `TimelineService`, `LikedService`）重複出現。

    *   **建議**: 建立一個 `App\Services\Chocotissue\Traits\ValidatesInput` Trait，將這些共用的驗證方法移入其中，然後讓需要的 Service use 這個 Trait。
    
