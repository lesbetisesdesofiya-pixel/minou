package com.example.operaalerte

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.lifecycleScope
import com.example.operaalerte.ui.theme.OperaAlerteTheme
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.launch

sealed interface Nav {
    data object Loading : Nav
    data object SiteSelect : Nav
    data object Setup : Nav
    data object Main : Nav
    data class Alert(val alert: OrderAlert, val token: String) : Nav
}

class MainActivity : ComponentActivity() {

    companion object {
        const val ACTION_ALERT = "opera.ALERT"
    }

    private var nav by mutableStateOf<Nav>(Nav.Loading)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        onBackPressedDispatcher.addCallback(this, object : androidx.activity.OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (nav is Nav.Alert) return // alarme : Accepter/Refuser obligatoire
                finish()
            }
        })
        handleIntent(intent)
        try { FirebaseMessaging.getInstance().subscribeToTopic("admin_alerts") } catch (_: Exception) {}
        setContent {
            OperaAlerteTheme {
                RequestNotifPermission()
                when (val n = nav) {
                    is Nav.Loading -> SplashBox()
                    is Nav.SiteSelect -> SiteScreen(onPick = { id ->
                        Sites.save(this, id)
                        TokenStore.clear(this) // le token est lié à un site
                        WatchService.stop(this)
                        handleIntent(null)
                    })
                    is Nav.Setup -> SetupScreen(onLoggedIn = { handleIntent(null) })
                    is Nav.Main -> MainScreen(
                        onTestAlarm = { testAlarm() },
                        onOpenDashboard = { openDeeplink("myopera://commandes") },
                        onChangeSite = {
                            Sites.clear(this)
                            TokenStore.clear(this)
                            WatchService.stop(this)
                            AlertSound.stop()
                            handleIntent(null)
                        },
                        onLogout = {
                            TokenStore.clear(this)
                            WatchService.stop(this)
                            AlertSound.stop()
                            handleIntent(null)
                        },
                    )
                    is Nav.Alert -> AlertScreen(
                        alert = n.alert,
                        onAccept = { acceptOrder(n) },
                        onRefuse = {
                            Alerts.dismiss(this, n.alert.id)
                            nav = Nav.Main
                        },
                    )
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleIntent(intent)
    }

    private fun handleIntent(intent: Intent?) {
        // Ordre strict : site (base API) AVANT token, sinon appels vers le mauvais site.
        val site = Sites.applyStored(this)
        if (site == null) {
            WatchService.stop(this)
            nav = Nav.SiteSelect
            return
        }
        val token = TokenStore.get(this)
        if (token.isNullOrEmpty()) {
            nav = Nav.Setup
            return
        }
        WatchService.start(this)
        if (intent?.action == ACTION_ALERT) {
            // Détail complet direct sur la page d'alerte (source fiable : l'API),
            // avec repli sur les données du push si le réseau flanche.
            val id = intent.getStringExtra("order_id") ?: return
            nav = Nav.Loading
            lifecycleScope.launch {
                try {
                    val detail = Api.getOrder(token, id)
                    nav = Nav.Alert(OrderAlert.fromOrderJson(detail), token)
                } catch (_: Exception) {
                    nav = Nav.Alert(OrderAlert.fromIntent(intent), token)
                }
            }
        } else if (nav is Nav.Loading || nav is Nav.Setup) {
            nav = Nav.Main
        }
    }

    /** Accepter : passe la commande en cuisine (API) puis ouvre le détail via deeplink. */
    private fun acceptOrder(n: Nav.Alert) {
        lifecycleScope.launch {
            try {
                Api.setStatus(n.token, n.alert.id, "PREPARING")
            } catch (_: Exception) {}
            Alerts.dismiss(this@MainActivity, n.alert.id)
            openDeeplink("myopera://commande/${n.alert.id}")
            nav = Nav.Main
        }
    }

    /** Test manuel : même chemin qu'une vraie alarme (son + écran). */
    private fun testAlarm() {
        val test = OrderAlert(
            id = "TEST",
            total = "16 000 FCFA",
            name = "Kouamé Jean-Marc",
            phone = "+225 07 88 45 12 90",
            neighborhood = "Cocody Danga",
            items = listOf(
                OrderItem("Bar grillé", 1, 12500.0),
                OrderItem("Cocktail Passion", 1, 3500.0),
            ),
            note = "Poisson bien grillé",
        )
        Alerts.raise(this, test)
        nav = Nav.Alert(test, TokenStore.get(this) ?: "")
    }

    private fun openDeeplink(uri: String) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(uri)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        } catch (_: Exception) {
            Toast.makeText(this, "Installe l'app Opéra Admin pour le détail.", Toast.LENGTH_LONG).show()
        }
    }
}
