package com.example.opera

import android.annotation.SuppressLint
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val Navy = Color(0xFF0A192F)
private val Orange = Color(0xFFF97316)
private val OrangeDeep = Color(0xFFEA580C)
private val PageBg = Color(0xFFF8F9FF)
private val Muted = Color(0xFF64748B)

@Composable
fun SplashBox() {
    Box(Modifier.fillMaxSize().background(PageBg), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = Orange)
    }
}

/** Connexion unique : email + password -> JWT stocké chiffré (le "code magique"). */
@Composable
fun SetupScreen(onLoggedIn: () -> Unit) {
    val ctx = LocalContext.current
    val scope = rememberCoroutineScope()
    var email by remember { mutableStateOf("admin@opera.com") }
    var password by remember { mutableStateOf("") }
    var err by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }

    Column(
        Modifier.fillMaxSize().background(PageBg).padding(24.dp),
        verticalArrangement = Arrangement.Center,
    ) {
        Text("Opéra Admin", fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Navy)
        Text("Connexion unique de l'appareil — plus jamais demandée.", color = Muted)
        Spacer(Modifier.height(16.dp))
        if (err.isNotEmpty()) Text(err, color = Color(0xFFB91C1C))
        OutlinedTextField(email, { email = it }, Modifier.fillMaxWidth(), label = { Text("Email admin") }, singleLine = true)
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(password, { password = it }, Modifier.fillMaxWidth(), label = { Text("Mot de passe") },
            visualTransformation = PasswordVisualTransformation(), singleLine = true)
        Spacer(Modifier.height(16.dp))
        Button(
            onClick = {
                busy = true; err = ""
                scope.launch {
                    try {
                        val (token, _) = Api.login(email.trim(), password)
                        TokenStore.save(ctx, token)
                        WatchService.start(ctx)
                        onLoggedIn()
                    } catch (e: Exception) {
                        err = e.message ?: "Identifiants invalides."
                    } finally { busy = false }
                }
            },
            enabled = !busy,
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = Navy),
        ) { Text(if (busy) "Connexion…" else "Connecter cet appareil") }
    }
}

/** Écran Alerte nouvelle commande — fidèle au design op_ra_alerte_nouvelle_commande_mobile. */
@Composable
fun AlertScreen(alert: OrderAlert, onAccept: () -> Unit, onRefuse: () -> Unit) {
    var left by remember { mutableStateOf(105) } // 01:45 dégressif
    var busy by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        while (left > 0) { delay(1000); left-- }
    }
    val mm = "%02d:%02d".format(left / 60, left % 60)

    Column(Modifier.fillMaxSize().background(PageBg).verticalScroll(rememberScrollState())) {
        // Bannière dégradé
        Box(
            Modifier.fillMaxWidth()
                .background(Brush.verticalGradient(listOf(OrangeDeep, Orange, Color(0xFFF59E0B))))
                .padding(20.dp),
            contentAlignment = Alignment.Center,
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("🔔", fontSize = 34.sp)
                Text("ALERTE ENTRANTE", color = Color.White.copy(alpha = 0.85f), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Text("Nouvelle commande !", color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold)
                Text("#OP-${alert.id} • $mm", color = Color.White, fontWeight = FontWeight.Bold)
            }
        }
        Column(Modifier.padding(16.dp)) {
            // Résumé
            Box(Modifier.fillMaxWidth().background(Color.White, RoundedCornerShape(16.dp)).padding(16.dp)) {
                Column {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(alert.total, fontWeight = FontWeight.ExtraBold, fontSize = 20.sp, color = Navy)
                        Text("Payé en ligne", color = Color(0xFF059669), fontWeight = FontWeight.Bold)
                    }
                    Spacer(Modifier.height(6.dp))
                    Text("${alert.name} • ${alert.phone}", color = Muted)
                    if (alert.neighborhood.isNotEmpty()) Text("📍 ${alert.neighborhood}", color = Muted)
                }
            }
            Spacer(Modifier.height(10.dp))
            // Articles
            Box(Modifier.fillMaxWidth().background(Color.White, RoundedCornerShape(16.dp)).padding(16.dp)) {
                Column {
                    Text("Articles (${alert.items.size})", fontWeight = FontWeight.Bold, color = Navy)
                    alert.items.forEach {
                        Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("${it.name} ×${it.qty}", color = Navy)
                            Text("${it.price.toLong()} F", fontWeight = FontWeight.Bold, color = Navy)
                        }
                    }
                    if (alert.items.isEmpty()) Text("Détail dans le dashboard.", color = Muted)
                }
            }
            Spacer(Modifier.height(14.dp))
            Button(
                onClick = { busy = true; onAccept() },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                colors = ButtonDefaults.buttonColors(containerColor = OrangeDeep),
            ) { Text("Accepter la commande", fontWeight = FontWeight.Bold) }
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = { busy = true; onRefuse() },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) { Text("Refuser la commande", color = Muted) }
        }
    }
}

/** Dashboard admin en WebView, déjà authentifié via ?token=. */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun DashboardScreen(url: String) {
    AndroidView(
        factory = { ctx ->
            WebView(ctx).apply {
                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true
                settings.mediaPlaybackRequiresUserGesture = false
                webViewClient = WebViewClient()
                loadUrl(url)
            }.also { MainActivity.webView = it }
        },
        update = { wv ->
            if (wv.url != url) wv.loadUrl(url)
            MainActivity.webView = wv
        },
        modifier = Modifier.fillMaxSize(),
    )
}

/** Permission notifications (Android 13+) demandée une fois. */
@Composable
fun RequestNotifPermission() {
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {}
    LaunchedEffect(Unit) {
        if (android.os.Build.VERSION.SDK_INT >= 33) {
            launcher.launch(android.Manifest.permission.POST_NOTIFICATIONS)
        }
    }
}
