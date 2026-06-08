#--------------------------------------------------------------------
# ENVIRONMENT
#--------------------------------------------------------------------
# Mengaktifkan mode development agar jika ada error di kodingan CI4, 
# pesan error-nya akan muncul di layar (bukan sekadar blank putih)
CI_ENVIRONMENT = development

#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------
# Ganti angka 192.168.1.15 dengan IP Address WiFi laptopmu.
# Pastikan ada garis miring (/) di bagian paling akhir URL.
app.baseURL = 'http://10.10.107.82:8080/'

# Kosongkan indexPage agar URL API kamu nantinya lebih bersih 
# (tanpa ada sisipan /index.php/ di tengah link)
app.indexPage = ''

#--------------------------------------------------------------------
# DATABASE
#--------------------------------------------------------------------
# Konfigurasi default menyambung ke XAMPP di laptop lokal
database.default.hostname = localhost
database.default.database = belajarin
database.default.username = root
database.default.password = 
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306

#--------------------------------------------------------------------
# SECURITY & CORS (Opsional untuk API)
#--------------------------------------------------------------------
# Sangat berguna jika API ini nantinya akan diakses oleh platform lain 
# selain aplikasi Flutter (misalnya website dashboard admin)
# app.forceGlobalSecureRequests = false
# app.CSPEnabled = false