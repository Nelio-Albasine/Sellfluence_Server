package com.find.influencers.classes.sendDataToDB

import retrofit2.Call
import retrofit2.http.Body
import retrofit2.http.POST

interface ApiService {
    @POST("sellfluence/script_for_testing.php")
    fun createUser(@Body userData: UserData): Call<UserResponse>
}

