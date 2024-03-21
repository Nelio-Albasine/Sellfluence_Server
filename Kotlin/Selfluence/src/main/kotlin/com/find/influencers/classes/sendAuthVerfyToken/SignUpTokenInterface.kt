package com.find.influencers.classes.sendAuthVerfyToken

import retrofit2.Call
import retrofit2.http.Body
import retrofit2.http.POST

interface SignUpTokenInterface {
    @POST("sellfluence/0/SignUp Authentication Token/db_send_signup_token.php")
    fun sendVerificationToken(
        @Body
        sendData: UserDataSignupToken
    ) : Call<SignUpVerifyTokenResponse>
}