package com.example.opera

import android.content.Intent
import android.os.Bundle
import android.webkit.WebView
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.lifecycleScope
import com.example.opera.ui.theme.OperaTheme
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.launch

sealed interface Nav {
    data object Loading : Nav
    data object Setup : Nav
    data class Dash(val url: String) : Nav
    data class Alert(val alert: OrderAlert, val token: String) : Nav
}

class MainActivity : ComponentActivity() {

    companion object {
        const val ACTION_ALERT = "opera.ALERT"
        const val ACTION_DETAIL = "opera.DETAIL"
        var webView: WebView? = null
    }

    private var nav by mutableStateOf<Nav>(Nav.Loading)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        onBackPressedDispatcher.addCallback(this, object : androidx.activity.OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (nav is Nav.Alert) return // alarme : Accepter/Refuser obligatoire
                if (webView?.canGoBack() == true) webView?.goBack() else finish()
            }
        })
        handleIntent(intent)
        try { FirebaseMessaging.getInstance().subscribeToTopic("admin_alerts") } catch (_: Exception) {}
        setContent {
            OperaTheme {
                RequestNotifPermission()
                when (val n = nav) {
                    is Nav.Loading -> SplashBox()
                    is Nav.Setup -> SetupScreen(onLoggedIn = { handleIntent(null) })
                    is Nav.Dash -> DashboardScreen(url = n.url)
                    is Nav.Alert -> AlertScreen(
                        alert = n.alert,
                        onAccept = { accept(n, "PREPARING") },
                        onRefuse = { accept(n, "Annulée") },
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
        val token = TokenStore.get(this)
        if (token.isNullOrEmpty()) {
            nav = Nav.Setup
            return
        }
        WatchService.start(this)
        when (intent?.action) {
            ACTION_ALERT -> nav = Nav.Alert(OrderAlert.fromIntent(intent), token)
            ACTION_DETAIL -> {
                val id = intent.getStringExtra("order_id") ?: return
                AlertSound.stop()
                webView = null
                nav = Nav.Dash(Api.detailUrl(id, token))
            }
            else -> if (nav is Nav.Loading || nav is Nav.Setup) {
                nav = Nav.Dash(Api.dashUrl(token))
            }
        }
    }

    private fun accept(n: Nav.Alert, status: String) {
        lifecycleScope.launch {
            try {
                Api.setStatus(n.token, n.alert.id, status)
            } catch (_: Exception) {}
            AlertSound.stop()
            try {
                NotificationHelper.cancel(this@MainActivity, n.alert.id.hashCode())
            } catch (_: Exception) {}
            webView = null
            nav = Nav.Dash(Api.detailUrl(n.alert.id, n.token))
        }
    }
}
