# sync-ftp-users.ps1

# โหลด .env (ถ้ามี)
if (Test-Path .env) {
    Get-Content .env | ForEach-Object {
        if ($_ -match '^([^=]+)=(.*)$') {
            Set-Item -Path Env:$($matches[1]) -Value $matches[2]
        }
    }
}

# กำหนด users จาก .env
$users = @{
    "${env:ADMIN_USER}" = "${env:ADMIN_PASS}"
    "user1" = "${env:USER1_PASS}"
    "user2" = "${env:USER2_PASS}"
    "user3" = "${env:USER3_PASS}"
}

# สร้าง content สำหรับ virtual_users.txt (username\npassword สลับบรรทัด)
$content = ""
foreach ($user in $users.Keys) {
    $pass = $users[$user]
    if ($user -and $pass) {
        $content += "$user`n$pass`n"
        Write-Host "Adding user: $user"
    }
}

# ส่ง content เข้า container แล้วสร้าง folder + db
$content | docker compose exec -i ftp bash -c @'
cat > /etc/vsftpd/virtual_users.txt

# สร้าง folder สำหรับแต่ละ user
while read -r user; do
    mkdir -p "/home/vsftpd/users/$user"
done < <(awk 'NR%2==1' /etc/vsftpd/virtual_users.txt)

# แปลงเป็น db
/usr/bin/db_load -T -t hash -f /etc/vsftpd/virtual_users.txt /etc/vsftpd/virtual_users.db

# แก้ permission (user ใน image คือ vsftpd)
chown -R vsftpd:vsftpd /home/vsftpd/users
'@

Write-Host "Sync เสร็จแล้ว! ลอง login FTP ด้วย user จาก .env ได้เลย"
Write-Host "ถ้าเปลี่ยน password ใน .env ให้รันสคริปต์นี้อีกครั้ง"