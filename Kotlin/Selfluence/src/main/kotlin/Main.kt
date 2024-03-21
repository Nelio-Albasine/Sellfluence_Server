import com.google.gson.GsonBuilder
import com.find.influencers.classes.sendDataToDB.ApiService
import com.find.influencers.classes.sendDataToDB.UserData
import com.find.influencers.classes.sendDataToDB.UserResponse
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import org.testng.Assert.assertTrue
import org.testng.annotations.Test
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

fun main() {
    sendRequestToDB { success, message ->
        println("Request result: $success, Message: $message")
    }
}

fun sendRequestToDB(callback: (Boolean, String) -> Unit) {
    // Configure Gson to be lenient
    val gson = GsonBuilder().setLenient().create()

    // Create a logging interceptor
    val logging = HttpLoggingInterceptor().apply {
        level = HttpLoggingInterceptor.Level.BODY
    }

    // Create an OkHttpClient and add the logging interceptor
    val client = OkHttpClient.Builder()
      /*  .addInterceptor(logging)
        .addNetworkInterceptor { chain ->
            val originalResponse = chain.proceed(chain.request())
            val responseBody = originalResponse.body
            val responseBodyString = responseBody.string() // Use !! to assert responseBody is non-null

            // Log the raw response body
            println("Raw response body: $responseBodyString")

            // To allow further processing, rebuild the response body and return it
            val newResponseBody = responseBodyString.toResponseBody(responseBody.contentType())
            originalResponse.newBuilder()
                .body(newResponseBody).build()
        } */
        .build()



    // Create the Retrofit instance with the OkHttpClient and lenient Gson
    val retrofit = Retrofit.Builder()
        .baseUrl("https://lcgdigital.com.br/")
        .client(client)
        .addConverterFactory(GsonConverterFactory.create(gson))
        .build()

    val service = retrofit.create(ApiService::class.java)

    //User data after Signup
    val userData = UserData(
        name = "Nelio Albasine",
        email = "nelioalbasine@gmail.com",
        id = 1234566)

    val call = service.createUser(userData)

    call.enqueue(object : Callback<UserResponse> {
        override fun onResponse(call: Call<UserResponse>, response: Response<UserResponse>) {
            val responseBody = response.body()?.toString() ?: "Response body is null"
            println("Successful response body: $responseBody")
            if (!response.isSuccessful) {
                // Attempt to read raw JSON directly for debugging purposes
                callback(false, "Failed with status code: ${response.code()}")
                return
            }
            val userResponse = response.body()!!
            println("User creation successful: ${userResponse.message}")
            callback(true, userResponse.message)
        }

        override fun onFailure(call: Call<UserResponse>, t: Throwable) {
            println("Request failed: ${t.message}")
            callback(false, "Request failed: ${t.message}")
        }
    })
}

class MainFunctionTest {
    @Test
    fun testUserCreationRequest() {
        val latch = CountDownLatch(1)
        var success = false

        sendRequestToDB { result, message ->
            success = result
            println(message)
            latch.countDown()
        }

        latch.await(10, TimeUnit.SECONDS) // Wait for the request to complete or timeout after 10 seconds
        assertTrue(success)
    }
}
