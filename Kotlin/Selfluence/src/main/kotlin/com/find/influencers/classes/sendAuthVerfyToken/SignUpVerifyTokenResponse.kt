package com.find.influencers.classes.sendAuthVerfyToken

data class SignUpVerifyTokenResponse (
    val success: Boolean,
    val message: String,
    val code: Int
)