-- View structure for view cat_relationship
--
-- WARNING: Add the correct prefix before each table name

CREATE VIEW IF NOT EXISTS cat_relationship AS select categorylinks.cl_to AS father,category.cat_title AS child,category.cat_subcats AS child_subcats from ((page join categorylinks FORCE INDEX (cl_sortkey) on(categorylinks.cl_from = page.page_id)) left join category on(category.cat_title = page.page_title and page.page_namespace = 14)) where categorylinks.cl_type = 'subcat' order by categorylinks.cl_type,categorylinks.cl_sortkey;

