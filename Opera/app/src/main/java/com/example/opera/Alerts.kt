package com.example.opera

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

/** Déclenchement unifié de l'alarme (FCM ou polling) : notif plein écran + son + écran Alerte. */
object Alerts {
    const val CHANNEL = "alarm_channel"

    fun ensureChannel(ctx: Context) {
        if (Build.VERSION.SDK_INT < 26) return
        val mgr = ctx.getSystemService(NotificationManager::class.java) ?: return
        if (mgr.getNotificationChannel(CHANNEL) != null) return
        mgr.createNotificationChannel(
            NotificationChannel(CHANNEL, "Alarmes commandes", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Alerte sonore à chaque nouvelle commande"
                enableVibration(true)
                lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
                setSound(null, null)
            }
        )
    }

    fun raise(ctx: Context, alert: OrderAlert) {
        ensureChannel(ctx)
        AlertSound.start(ctx)
        val intent = Intent(ctx, MainActivity::class.java)
            .setAction(MainActivity.ACTION_ALERT)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        OrderAlert.toIntent(intent, alert)
        val pi = PendingIntent.getActivity(
            ctx, alert.id.hashCode(), intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        val notif = NotificationCompat.Builder(ctx, CHANNEL)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle("Nouvelle commande ! #OP-${alert.id}")
            .setContentText("${alert.total} — ${alert.name}")
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_ALARM)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setAutoCancel(true)
            .setContentIntent(pi)
            .setFullScreenIntent(pi, true)
            .build()
        try {
            if (NotificationManagerCompat.from(ctx).areNotificationsEnabled()) {
                NotificationManagerCompat.from(ctx).notify(alert.id.hashCode(), notif)
            }
        } catch (_: SecurityException) {}
    }
}
