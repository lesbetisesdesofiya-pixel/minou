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
    data object Livreur : Nav
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
                        onOpenDashboard = { openDeeplink("myopera://commandes") },
                    )
                    is Nav.Livreur -> LivreurScreen(
                        onOpenDashboard = { openDeeplink("myopera://livreur/commandes") },
                    )
                    is Nav.Alert -> AlertScreen(
                        alert = n.alert,
                        onAccept = { acceptOrder(n) },
                        onRefuse = {
                            Alerts.dismiss(this, n.alert.id)
                            nav = homeNav()
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

    private fun homeNav(): Nav =
        if (TokenStore.getRole(this) == "DELIVERY") Nav.Livreur else Nav.Main

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
        // Résout rôle + livreur (abonnements push par rôle), puis route.
        nav = Nav.Loading
        lifecycleScope.launch {
            try {
                val me = Api.me(token)
                val role = me.optString("role", "")
                TokenStore.saveRole(this@MainActivity, role)
                val driverId = me.optInt("delivery_person_id", 0)
                TokenStore.saveDriverId(this@MainActivity, driverId)
                try {
                    val fm = FirebaseMessaging.getInstance()
                    if (role == "DELIVERY" && driverId > 0) {
                        // Livreurs : uniquement SES courses prêtes (plus d'alarmes cuisine).
                        try { fm.unsubscribeFromTopic("admin_alerts") } catch (_: Exception) {}
                        fm.subscribeToTopic("driver_$driverId")
                    } else {
                        fm.subscribeToTopic("admin_alerts")
                    }
                } catch (_: Exception) {}
            } catch (_: Exception) {
                // réseau flanche : on garde le rôle stocké
            }
            WatchService.start(this@MainActivity)
            if (intent?.action == ACTION_ALERT) {
                // Détail complet direct sur la page d'alerte (source fiable : l'API),
                // avec repli sur les données du push si le réseau flanche.
                val id = intent.getStringExtra("order_id")
                if (id == null) {
                    nav = homeNav()
                } else {
                    try {
                        val detail = Api.getOrder(token, id)
                        nav = Nav.Alert(OrderAlert.fromOrderJson(detail), token)
                    } catch (_: Exception) {
                        nav = Nav.Alert(OrderAlert.fromIntent(intent), token)
                    }
                }
            } else if (nav is Nav.Loading || nav is Nav.Setup) {
                nav = homeNav()
            }
        }
    }

    /**
     * Accepter : admin -> passe en cuisine + ouvre le détail ;
     * livreur -> ouvre simplement ses courses (pas de changement de statut).
     */
    private fun acceptOrder(n: Nav.Alert) {
        lifecycleScope.launch {
            val isLivreur = TokenStore.getRole(this@MainActivity) == "DELIVERY"
            if (!isLivreur) {
                try {
                    Api.setStatus(n.token, n.alert.id, "PREPARING")
                } catch (_: Exception) {}
            }
            Alerts.dismiss(this@MainActivity, n.alert.id)
            if (isLivreur) openDeeplink("myopera://livreur/commandes")
            else openDeeplink("myopera://commande/${n.alert.id}")
            nav = homeNav()
        }
    }

    private fun openDeeplink(uri: String) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(uri)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        } catch (_: Exception) {
            Toast.makeText(this, "Installe l'app Opéra Admin pour le détail.", Toast.LENGTH_LONG).show()
        }
    }
}
