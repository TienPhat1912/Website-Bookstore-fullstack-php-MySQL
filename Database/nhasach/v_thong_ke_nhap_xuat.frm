TYPE=VIEW
query=select `s`.`id` AS `sach_id`,`s`.`ma_sach` AS `ma_sach`,`s`.`ten` AS `ten`,coalesce((select sum(`ct`.`so_luong`) from (`nhasach`.`chi_tiet_phieu_nhap` `ct` join `nhasach`.`phieu_nhap` `pn` on(`pn`.`id` = `ct`.`phieu_nhap_id`)) where `ct`.`sach_id` = `s`.`id` and `pn`.`trang_thai` = \'done\'),0) AS `tong_nhap`,coalesce((select sum(`ct`.`so_luong`) from (`nhasach`.`chi_tiet_don_hang` `ct` join `nhasach`.`don_hang` `dh` on(`dh`.`id` = `ct`.`don_hang_id`)) where `ct`.`sach_id` = `s`.`id` and `dh`.`trang_thai` <> \'da_huy\'),0) AS `tong_xuat`,`s`.`so_luong` AS `ton_kho_thuc_te` from `nhasach`.`sach` `s`
md5=e6acaea42e9dce79ede4400845b9ecfb
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=0001772782267613609
create-version=2
source=SELECT\n  s.id AS sach_id, s.ma_sach, s.ten,\n  COALESCE((\n    SELECT SUM(ct.so_luong) FROM chi_tiet_phieu_nhap ct\n    JOIN phieu_nhap pn ON pn.id = ct.phieu_nhap_id\n    WHERE ct.sach_id = s.id AND pn.trang_thai = \'done\'\n  ), 0) AS tong_nhap,\n  COALESCE((\n    SELECT SUM(ct.so_luong) FROM chi_tiet_don_hang ct\n    JOIN don_hang dh ON dh.id = ct.don_hang_id\n    WHERE ct.sach_id = s.id AND dh.trang_thai != \'da_huy\'\n  ), 0) AS tong_xuat,\n  s.so_luong AS ton_kho_thuc_te\nFROM sach s
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_unicode_ci
view_body_utf8=select `s`.`id` AS `sach_id`,`s`.`ma_sach` AS `ma_sach`,`s`.`ten` AS `ten`,coalesce((select sum(`ct`.`so_luong`) from (`nhasach`.`chi_tiet_phieu_nhap` `ct` join `nhasach`.`phieu_nhap` `pn` on(`pn`.`id` = `ct`.`phieu_nhap_id`)) where `ct`.`sach_id` = `s`.`id` and `pn`.`trang_thai` = \'done\'),0) AS `tong_nhap`,coalesce((select sum(`ct`.`so_luong`) from (`nhasach`.`chi_tiet_don_hang` `ct` join `nhasach`.`don_hang` `dh` on(`dh`.`id` = `ct`.`don_hang_id`)) where `ct`.`sach_id` = `s`.`id` and `dh`.`trang_thai` <> \'da_huy\'),0) AS `tong_xuat`,`s`.`so_luong` AS `ton_kho_thuc_te` from `nhasach`.`sach` `s`
mariadb-version=100432
