package com.example.opera

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
 * Surveillance secours (sans FCM) : service foreground qui interroge
 * GET /api/orders toutes les 20 s et déclenche l'alarme sur les nouvelles "pending".
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
            .setContentTitle("Opéra Admin : surveillance des commandes")
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

    private suspend fun check() {
        val token = TokenStore.get(this) ?: return
        val list = Api.listOrders(token, 10)
        val seen = TokenStore.seenIds(this)
        for (i in 0 until list.length()) {
            val o = list.optJSONObject(i) ?: continue
            val id = o.opt("id")?.toString() ?: continue
            val status = o.optString("status", "")
            if (status != "pending") continue
            if (seen.contains(id) || seen.contains("fcm-$id")) continue
            TokenStore.addSeen(this, id)
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
