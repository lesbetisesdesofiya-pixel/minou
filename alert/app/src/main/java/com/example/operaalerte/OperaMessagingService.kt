package com.example.operaalerte

import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

/** Push FCM (topic admin_alerts) -> alarme plein écran, même app fermée / téléphone verrouillé. */
class OperaMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        try { FirebaseMessaging.getInstance().subscribeToTopic("admin_alerts") } catch (_: Exception) {}
    }

    override fun onMessageReceived(msg: RemoteMessage) {
        val d = msg.data
        val orderId = d["order_id"] ?: return
        if (TokenStore.get(this).isNullOrEmpty()) return // appareil non configuré : rien à alerter
        val type = d["type"] ?: "new_order"
        if (type != "new_order" && type != "paid") return
        TokenStore.addSeen(this, "fcm-$orderId")
        Alerts.raise(this, OrderAlert.fromData(d))
    }
}
