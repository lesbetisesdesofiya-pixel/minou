package com.example.opera

import android.content.Context
import androidx.core.app.NotificationManagerCompat

object NotificationHelper {
    fun cancel(ctx: Context, id: Int) {
        try { NotificationManagerCompat.from(ctx).cancel(id) } catch (_: Exception) {}
    }
}
