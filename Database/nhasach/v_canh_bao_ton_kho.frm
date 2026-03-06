TYPE=VIEW
query=select `nhasach`.`sach`.`id` AS `id`,`nhasach`.`sach`.`ma_sach` AS `ma_sach`,`nhasach`.`sach`.`ten` AS `ten`,`nhasach`.`sach`.`so_luong` AS `so_luong` from `nhasach`.`sach` where `nhasach`.`sach`.`so_luong` <= 5 and `nhasach`.`sach`.`hien_trang` = 1
md5=790d7346f87dd4b3023623bfad3618fd
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=0001772782267595982
create-version=2
source=SELECT id, ma_sach, ten, so_luong\nFROM sach\nWHERE so_luong <= 5 AND hien_trang = 1
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_unicode_ci
view_body_utf8=select `nhasach`.`sach`.`id` AS `id`,`nhasach`.`sach`.`ma_sach` AS `ma_sach`,`nhasach`.`sach`.`ten` AS `ten`,`nhasach`.`sach`.`so_luong` AS `so_luong` from `nhasach`.`sach` where `nhasach`.`sach`.`so_luong` <= 5 and `nhasach`.`sach`.`hien_trang` = 1
mariadb-version=100432
