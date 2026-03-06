TYPE=VIEW
query=select `s`.`id` AS `id`,`s`.`ma_sach` AS `ma_sach`,`s`.`ten` AS `ten`,`s`.`tac_gia` AS `tac_gia`,`tl`.`ten` AS `the_loai`,`s`.`so_luong` AS `so_luong`,`s`.`gia_nhap` AS `gia_nhap`,`s`.`ty_le_ln` AS `ty_le_ln`,round(`s`.`gia_nhap` * (1 + `s`.`ty_le_ln` / 100),0) AS `gia_ban`,`s`.`hien_trang` AS `hien_trang`,`s`.`da_nhap_hang` AS `da_nhap_hang` from (`nhasach`.`sach` `s` join `nhasach`.`the_loai` `tl` on(`s`.`the_loai_id` = `tl`.`id`))
md5=2208f6fa103c4b2ab6c03b2c618c7f05
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=0001772782267582608
create-version=2
source=SELECT\n  s.id, s.ma_sach, s.ten, s.tac_gia,\n  tl.ten                                              AS the_loai,\n  s.so_luong, s.gia_nhap, s.ty_le_ln,\n  ROUND(s.gia_nhap * (1 + s.ty_le_ln / 100), 0)      AS gia_ban,\n  s.hien_trang, s.da_nhap_hang\nFROM sach s\nJOIN the_loai tl ON s.the_loai_id = tl.id
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_unicode_ci
view_body_utf8=select `s`.`id` AS `id`,`s`.`ma_sach` AS `ma_sach`,`s`.`ten` AS `ten`,`s`.`tac_gia` AS `tac_gia`,`tl`.`ten` AS `the_loai`,`s`.`so_luong` AS `so_luong`,`s`.`gia_nhap` AS `gia_nhap`,`s`.`ty_le_ln` AS `ty_le_ln`,round(`s`.`gia_nhap` * (1 + `s`.`ty_le_ln` / 100),0) AS `gia_ban`,`s`.`hien_trang` AS `hien_trang`,`s`.`da_nhap_hang` AS `da_nhap_hang` from (`nhasach`.`sach` `s` join `nhasach`.`the_loai` `tl` on(`s`.`the_loai_id` = `tl`.`id`))
mariadb-version=100432
