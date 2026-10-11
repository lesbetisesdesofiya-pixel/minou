package com.example.operaalerte

import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

/** Push FCM (topic admin_alerts) -> alarme plein écran, même app fermée / téléphone verrouillé. */
class OperaMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        try {
            val fm = FirebaseMessaging.getInstance()
            if (TokenStore.getRole(this) == "DELIVERY") {
                val d = TokenStore.getDriverId(this)
                if (d > 0) fm.subscribeToTopic("driver_$d")
            } else {
                fm.subscribeToTopic("admin_alerts")
            }
        } catch (_: Exception) {}
    }

    override fun onMessageReceived(msg: RemoteMessage) {
        val d = msg.data
        val orderId = d["order_id"] ?: return
        if (TokenStore.get(this).isNullOrEmpty()) return // appareil non configuré : rien à alerter
        val type = d["type"] ?: "new_order"
        // new_order/paid = cuisine ; ready = course prête pour le livreur assigné
        if (type != "new_order" && type != "paid" && type != "ready") return
        // Un livreur ne sonne que pour SES courses (sécurité anti-bruit).
        if (type == "ready" && TokenStore.getRole(this) == "DELIVERY") {
            val mine = TokenStore.getDriverId(this)
            if (mine <= 0) return
        }
        TokenStore.addSeen(this, "fcm-$orderId")
        Alerts.raise(this, OrderAlert.fromData(d))
    }
}
