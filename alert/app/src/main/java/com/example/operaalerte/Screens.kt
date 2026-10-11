package com.example.operaalerte

import android.Manifest
import android.os.Build
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
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.EditNote
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.SetMeal
import androidx.compose.material.icons.filled.Timer
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
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val Navy = Color(0xFF0A192F)
private val Orange = Color(0xFFF97316)
private val OrangeDeep = Color(0xFFEA580C)
private val PageBg = Color(0xFFF8F9FF)
private val Muted = Color(0xFF64748B)
private val Green = Color(0xFF059669)

@Composable
fun SplashBox() {
    Box(Modifier.fillMaxSize().background(PageBg), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = Orange)
    }
}

@Composable
fun RequestNotifPermission() {
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {}
    LaunchedEffect(Unit) {
        if (Build.VERSION.SDK_INT >= 33) launcher.launch(Manifest.permission.POST_NOTIFICATIONS)
    }
}

/** Connexion unique : email + password -> JWT chiffré (le "code magique"). */
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
        Text("Opéra Alerte", fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Navy)
        Text("Connexion unique de l'appareil cuisine — alarme à chaque commande.", color = Muted)
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
                        val (token, _) = Api.login(email.trim(), password.trim())
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
        ) { Text(if (busy) "Connexion…" else "Activer les alarmes") }
    }
}

/** Choix unique du restaurant AVANT le login (détermine l'URL de l'API). */
@Composable
fun SiteScreen(onPick: (String) -> Unit) {
    Column(
        Modifier.fillMaxSize().background(PageBg).padding(24.dp),
        verticalArrangement = Arrangement.Center,
    ) {
        Text("Opéra Alerte", fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Navy)
        Text("Choisis ton restaurant (une seule fois).", color = Muted)
        Spacer(Modifier.height(16.dp))
        Sites.ALL.forEach { s ->
            Button(onClick = { onPick(s.id) }, modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Navy)) {
                Column(horizontalAlignment = Alignment.Start, modifier = Modifier.fillMaxWidth()) {
                    Text(s.label, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                    Text(s.host, fontSize = 12.sp, color = Color.White.copy(alpha = 0.7f))
                }
            }
        }
    }
}

/** Écran principal : état de surveillance + dashboard. */
@Composable
fun MainScreen(onOpenDashboard: () -> Unit) {
    Column(
        Modifier.fillMaxSize().background(PageBg).padding(24.dp),
        verticalArrangement = Arrangement.Center,
    ) {
        Text("Opéra Alerte", fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Navy)
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.padding(end = 8.dp).background(Green, RoundedCornerShape(4.dp)).padding(4.dp))
            Text("Surveillance active — sonne à chaque commande.", color = Muted)
        }
        Spacer(Modifier.height(16.dp))
        OutlinedButton(onClick = onOpenDashboard, modifier = Modifier.fillMaxWidth()) {
            Text("Ouvrir le dashboard", color = Navy)
        }
    }
}

/**
 * Écran Alerte nouvelle commande — design op_ra_alerte_nouvelle_commande_mobile :
 * bannière dégradé orange + timer 01:45, carte commande, Accepter / Refuser.
 */
@Composable
fun AlertScreen(alert: OrderAlert, onAccept: () -> Unit, onRefuse: () -> Unit) {
    var left by remember { mutableStateOf(105) } // 01:45 dégressif (visuel design)
    var busy by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        while (left > 0) { delay(1000); left-- }
    }
    val mm = "%02d:%02d".format(left / 60, left % 60)

    Column(Modifier.fillMaxSize().background(PageBg).verticalScroll(rememberScrollState())) {
        // Bannière dégradé orange (design) + cloche navy
        Box(
            Modifier.fillMaxWidth()
                .background(Brush.verticalGradient(listOf(OrangeDeep, Orange, Color(0xFFF59E0B))))
                .padding(20.dp),
            contentAlignment = Alignment.Center,
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Box(
                    Modifier.background(Navy.copy(alpha = 0.25f), RoundedCornerShape(999.dp)).padding(12.dp),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.NotificationsActive, contentDescription = "Alerte",
                        tint = Color.White, modifier = Modifier.padding(2.dp))
                }
                Spacer(Modifier.height(8.dp))
                Text("ALERTE ENTRANTE", color = Color.White.copy(alpha = 0.85f), fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Text("Nouvelle commande !", color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.ExtraBold)
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.background(Navy, RoundedCornerShape(999.dp)).padding(horizontal = 14.dp, vertical = 6.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.Timer, contentDescription = null, tint = Color.White)
                            Spacer(Modifier.padding(2.dp))
                            Text("#OP-${alert.id} • $mm", color = Color.White, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
        Column(Modifier.padding(16.dp)) {
            // Carte commande
            Box(Modifier.fillMaxWidth().background(Color.White, RoundedCornerShape(16.dp)).padding(16.dp)) {
                Column {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically) {
                        Text(alert.total, fontWeight = FontWeight.ExtraBold, fontSize = 20.sp, color = Navy)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Green)
                            Spacer(Modifier.padding(2.dp))
                            Text("Payé en ligne", color = Green, fontWeight = FontWeight.Bold)
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    InfoRow(Icons.Filled.Person, "${alert.name} • ${alert.phone}")
                    if (alert.neighborhood.isNotEmpty()) InfoRow(Icons.Filled.LocationOn, alert.neighborhood)
                    if (alert.note.isNotEmpty()) InfoRow(Icons.Filled.EditNote, alert.note)
                    if (alert.fish.isNotEmpty()) Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.SetMeal, contentDescription = null, tint = Color(0xFF0284C7))
                        Spacer(Modifier.padding(2.dp))
                        Text("Poisson : ${alert.fish}", color = Color(0xFF0284C7), fontWeight = FontWeight.Bold)
                    }
                }
            }
            Spacer(Modifier.height(10.dp))
            // Carte articles
            Box(Modifier.fillMaxWidth().background(Color.White, RoundedCornerShape(16.dp)).padding(16.dp)) {
                Column {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.ReceiptLong, contentDescription = null, tint = Navy)
                        Spacer(Modifier.padding(3.dp))
                        Text("Articles (${alert.items.size})", fontWeight = FontWeight.Bold, color = Navy)
                    }
                    Spacer(Modifier.height(4.dp))
                    alert.items.forEach {
                        Column(Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("${it.name} ×${it.qty}", color = Navy)
                                Text("${it.price.toLong()} F", fontWeight = FontWeight.Bold, color = Navy)
                            }
                            if (it.options.isNotEmpty()) Text(it.options, color = Muted, fontSize = 12.sp)
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
            ) {
                Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Color.White)
                Spacer(Modifier.padding(4.dp))
                Text("Accepter la commande", fontWeight = FontWeight.Bold)
            }
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = { busy = true; onRefuse() },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                Icon(Icons.Filled.Cancel, contentDescription = null, tint = Navy)
                Spacer(Modifier.padding(4.dp))
                Text("Refuser (couper l'alarme)", color = Navy)
            }
        }
    }
}

@Composable
private fun InfoRow(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, contentDescription = null, tint = Muted)
        Spacer(Modifier.padding(2.dp))
        Text(text, color = Muted)
    }
}
