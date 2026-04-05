$ftpServer = "ftp://ftp.ligawocdominicana.com/public_html"
$user = "ligaojmj"
$password = "v5sh7hXN99vh"

$webclient = New-Object System.Net.WebClient
$webclient.Credentials = New-Object System.Net.NetworkCredential($user, $password)

try {
    Write-Host "Subiendo checker.php..."
    $webclient.UploadFile("$ftpServer/checker.php", "C:\xampp\htdocs\LIGAWOC\checker.php")
    Write-Host "checker.php OK" -ForegroundColor Green
}
catch {
    Write-Host "Error subiendo checker.php: $($_.Exception.Message)" -ForegroundColor Red
}
