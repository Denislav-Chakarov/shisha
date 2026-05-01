# Test Backlog and Confidence Ladder

This backlog is ordered by risk reduction and operational value.

## Priority Backlog (20 Tests)

1. **`test_guest_is_redirected_from_dashboard_routes`** (Feature)  
   Assert unauthenticated access to `/dashboard`, `/products`, `/tables`, `/reports` redirects to login.

2. **`test_authenticated_user_can_open_dashboard`** (Feature)  
   Assert logged-in user receives 200 on `/dashboard`.

3. **`test_login_with_valid_credentials_regenerates_session_and_updates_last_seen`** (Feature)  
   Assert auth success, session regen behavior, and `users.last_seen_at` update.

4. **`test_login_rejects_invalid_credentials`** (Feature)  
   Assert invalid credentials return error and keep user unauthenticated.

5. **`test_logout_clears_auth_invalidates_session_and_nulls_last_seen`** (Feature)  
   Assert full logout lifecycle and presence cleanup.

6. **`test_add_order_item_creates_open_order_updates_total_and_decrements_stock`** (Feature)  
   Assert open order creation/reuse, item insert, stock decrement, and total recompute.

7. **`test_add_order_item_rejects_insufficient_stock_without_partial_writes`** (Feature)  
   Assert transactional integrity when stock is too low.

8. **`test_close_order_marks_paid_sets_closed_at_and_restores_hookah_stock_only`** (Feature)  
   Assert close semantics and selective stock restoration.

9. **`test_set_table_status_blocked_when_open_order_exists`** (Feature)  
   Assert status mutation is blocked with active open order.

10. **`test_create_reservation_rejects_overlapping_active_window`** (Feature)  
    Assert overlap conflict handling on `table_reservations`.

11. **`test_create_reservation_marks_table_reserved_and_persists_people_count`** (Feature)  
    Assert reservation insert and table status update behavior.

12. **`test_set_table_count_adds_missing_table_numbers_without_duplicates`** (Feature)  
    Assert table layout endpoint fills gaps deterministically.

13. **`test_products_page_applies_category_search_sort_and_view_fallback`** (Feature)  
    Assert filtering/sorting and invalid-view fallback to list.

14. **`test_add_product_applies_category_normalization_rules`** (Feature)  
    Assert hookah name normalization and tobacco price/unit normalization.

15. **`test_update_product_rejects_case_insensitive_duplicate_name_per_brand`** (Feature)  
    Assert duplicate prevention aligns with business rule.

16. **`test_image_suggestions_returns_empty_for_short_query_and_unique_paths_for_valid_query`** (Feature)  
    Assert endpoint behavior for short and valid terms with deduping.

17. **`test_reports_date_inputs_are_normalized_and_swapped_range_is_corrected`** (Feature)  
    Assert date parsing fallback/swap and stable aggregates.

18. **`test_workers_presence_marks_online_by_threshold_and_current_user_flag`** (Feature)  
    Assert presence payload online/offline logic.

19. **`test_parse_ai_order_text_handles_quantity_words_and_segment_split`** (Unit)  
    Assert parser extracts quantities/tokens correctly from mixed input.

20. **`test_resolve_hookah_flavors_corrects_near_matches_and_reports_unknowns`** (Unit)  
    Assert fuzzy flavor resolution and unknown-token reporting.

## Suggested Fixture Builders
- `userWithUsername()`
- `activeTable()`
- `brand(category)`
- `product(category, stock, price, active)`
- `openOrderWithItems(table, items)`
- `reservation(table, from, to, status)`

Use `RefreshDatabase` for all feature tests touching transactional stock/order logic.

## Confidence Ladder

### Milestone A: Auth Baseline Green
- Includes tests 1-5.
- Safe change scope: auth views, redirects, login/logout internals.

### Milestone B: Order Integrity Green
- Includes A + tests 6-10.
- Safe change scope: order append/close flows, stock updates, table-status guarding.

### Milestone C: Catalog and Reservation Green
- Includes B + tests 11-16.
- Safe change scope: product forms/rules, reservation UX and table layout behavior.

### Milestone D: Reporting and Presence Green
- Includes C + tests 17-18.
- Safe change scope: reporting filters/queries and presence refresh behavior.

### Milestone E: AI Parser Reliability Green
- Includes D + tests 19-20.
- Safe change scope: parser grammar, token normalization, matching heuristics.

