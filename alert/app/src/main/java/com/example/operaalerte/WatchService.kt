package com.example.operaalerte

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * Surveillance secours (FCM absent/pas encore configuré) : service foreground qui
 * interroge GET /api/orders toutes les 20 s et déclenche l'alarme sur les nouvelles "pending".
 */
class WatchService : Service() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var job: Job? = null

    override fun onCreate() {
        super.onCreate()
        if (Build.VERSION.SDK_INT >= 26) {
            val mgr = getSystemService(NotificationManager::class.java)
            if (mgr?.getNotificationChannel("watch_channel") == null) {
                mgr?.createNotificationChannel(
                    NotificationChannel("watch_channel", "Surveillance", NotificationManager.IMPORTANCE_LOW)
                )
            }
        }
        val notif = NotificationCompat.Builder(this, "watch_channel")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle("Opéra Alerte : surveillance des commandes")
            .setOngoing(true)
            .build()
        startForeground(1001, notif)
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (job == null) job = scope.launch { loop() }
        return START_STICKY
    }

    private suspend fun loop() {
        while (scope.isActive) {
            try { check() } catch (_: Exception) {}
            delay(20_000)
        }
    }

    // Statuts "nouvelle commande" : pending + paiement désactivé (Payée direct)
    // + attente de paiement (si réactivé). Garde-fou récence 30 min pour ne pas
    // sonner sur de vieilles commandes jamais vues (ex. 1er lancement).
    private val NEW_STATUSES = setOf("pending", "En attente de paiement", "Payée")
    private val MAX_AGE_MS = 30 * 60 * 1000L

    private fun isRecent(o: org.json.JSONObject): Boolean {
        val raw = o.optString("created_at", "")
        if (raw.isEmpty()) return true
        return try {
            // java.time = API 26+ ; en dessous on considère récent (pas de crash).
            if (android.os.Build.VERSION.SDK_INT < 26) return true
            val instant = java.time.Instant.parse(raw)
            System.currentTimeMillis() - instant.toEpochMilli() < MAX_AGE_MS
        } catch (_: Throwable) {
            true
        }
    }

    private suspend fun check() {
        val token = TokenStore.get(this) ?: return
        val list = Api.listOrders(token, 10)
        val seen = TokenStore.seenIds(this)
        // Mode livreur : uniquement MES courses PRÊTES. Sinon : nouvelles commandes cuisine.
        val isLivreur = TokenStore.getRole(this) == "DELIVERY"
        val myDriver = TokenStore.getDriverId(this)
        for (i in 0 until list.length()) {
            val o = list.optJSONObject(i) ?: continue
            val id = o.opt("id")?.toString() ?: continue
            val status = o.optString("status", "")
            if (isLivreur) {
                if (myDriver <= 0) return
                if (o.optInt("assigned_driver_id", 0) != myDriver) continue
                if (status != "READY_FOR_PICKUP") continue
            } else {
                if (status !in NEW_STATUSES) continue
            }
            if (seen.contains(id) || seen.contains("fcm-$id")) continue
            TokenStore.addSeen(this, id)
            if (!isRecent(o)) continue
            try {
                val detail = Api.getOrder(token, id)
                Alerts.raise(this, OrderAlert.fromOrderJson(detail))
            } catch (_: Exception) {}
            break // une alarme à la fois
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onDestroy() {
        job?.cancel()
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        fun start(ctx: Context) {
            try {
                val i = Intent(ctx, WatchService::class.java)
                if (Build.VERSION.SDK_INT >= 26) ctx.startForegroundService(i)
                else ctx.startService(i)
            } catch (_: Exception) {}
        }

        fun stop(ctx: Context) {
            try { ctx.stopService(Intent(ctx, WatchService::class.java)) } catch (_: Exception) {}
        }
    }
}
